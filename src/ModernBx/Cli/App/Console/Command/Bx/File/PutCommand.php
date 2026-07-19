<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\File;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteFilePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PutCommand extends AppCommand
{
    protected static $defaultName = 'file:put';

    protected RemoteProjectConfigManager $remoteProjectConfigManager;

    protected BitrixAdminClient $bitrixAdminClient;

    protected RemoteFilePhpCodeBuilder $remoteFilePhpCodeBuilder;

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteFilePhpCodeBuilder $remoteFilePhpCodeBuilder
    ) {
        parent::__construct();

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteFilePhpCodeBuilder = $remoteFilePhpCodeBuilder;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Загружает локальный файл в файловую структуру удаленного проекта')
            ->setHelp('Команда загружает локальный файл в путь относительно document root удаленного проекта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Удалить удаленный файл перед загрузкой')
            ->addArgument('src', InputArgument::REQUIRED, 'Путь к локальному файлу')
            ->addArgument('dest', InputArgument::REQUIRED, 'Удаленный путь относительно document root проекта');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        $remote = $input->getOption('remote');
        $srcArgument = $input->getArgument('src');
        $destArgument = $input->getArgument('dest');

        if (!is_string($remote) || trim($remote) === '') {
            throw new \RuntimeException('Опция --remote обязательна.', static::CODE_INVALID_OPTION_VALUE);
        }

        if (!is_string($srcArgument) || !is_string($destArgument)) {
            throw new \RuntimeException(
                'Аргументы src и dest должны быть строками.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $src = $this->resolveSourcePath($srcArgument);
        [$remoteDirectory, $filename] = $this->resolveRemoteDestination($destArgument, basename($src));
        $this->executeRemote($remote, $src, $remoteDirectory, $filename, $input->getOption('force') === true);
    }

    protected function executeRemote(
        string $codename,
        string $src,
        string $remoteDirectory,
        string $filename,
        bool $force
    ): void {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        [$sessionId, $limits] = $this->loadRemoteUploadLimitsWithRefresh($codename, $config, $endpoint, $sessionId);

        $size = filesize($src);

        if ($size === false) {
            throw new \RuntimeException(sprintf('Не удалось определить размер файла: %s', $src), static::CODE_IO_ERROR);
        }

        if ($force) {
            try {
                $this->deleteRemoteFile($endpoint, $sessionId, rtrim($remoteDirectory, '/') . '/' . $filename);
            } catch (\RuntimeException $err) {
                if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                    throw $err;
                }

                $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
                $this->deleteRemoteFile($endpoint, $sessionId, rtrim($remoteDirectory, '/') . '/' . $filename);
            }
        }

        if ($this->shouldUploadInChunks($size, $limits['max_post_file_bytes'])) {
            $sessionId = $this->uploadFileInChunks(
                $codename,
                $config,
                $endpoint,
                $sessionId,
                $src,
                $size,
                $remoteDirectory,
                $filename,
                $limits['max_post_file_bytes'],
            );
        } else {
            try {
                $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $src, $remoteDirectory, $filename);
            } catch (\RuntimeException $err) {
                if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                    throw $err;
                }

                $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
                $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $src, $remoteDirectory, $filename);
            }
        }

        $this->printer->info(sprintf('Файл загружен: %s/%s', rtrim($remoteDirectory, '/'), $filename));
    }

    /**
     * @param mixed[] $config
     * @return array{0: string, 1: array{upload_max_filesize: string, post_max_size: string, max_post_file_bytes: int}}
     */
    protected function loadRemoteUploadLimitsWithRefresh(
        string $codename,
        array $config,
        string $endpoint,
        string $sessionId
    ): array {
        try {
            return [$sessionId, $this->loadRemoteUploadLimits($endpoint, $sessionId)];
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);

            return [$sessionId, $this->loadRemoteUploadLimits($endpoint, $sessionId)];
        }
    }

    protected function shouldUploadInChunks(int $size, int $limit): bool
    {
        return $limit > 0 && $size > $this->getChunkSize($limit);
    }

    protected function getChunkSize(int $limit): int
    {
        $reserve = max(65536, (int) ceil($limit * 0.05));
        $chunkSize = $limit - $reserve;

        if ($chunkSize < 1) {
            throw new \RuntimeException(
                sprintf('Лимит загрузки PHP слишком мал для передачи файла частями: %d байт.', $limit),
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        return $chunkSize;
    }

    /** @param mixed[] $config */
    protected function uploadFileInChunks(
        string $codename,
        array $config,
        string $endpoint,
        string $sessionId,
        string $src,
        int $size,
        string $remoteDirectory,
        string $filename,
        int $limit
    ): string {
        $chunkSize = $this->getChunkSize($limit);
        $chunkCount = (int) ceil($size / $chunkSize);
        $prefix = '.' . $filename . '.upload-' . bin2hex(random_bytes(8));
        $remoteChunks = [];
        $source = fopen($src, 'rb');

        if ($source === false) {
            throw new \RuntimeException(sprintf('Не удалось открыть файл для чтения: %s', $src), static::CODE_IO_ERROR);
        }

        try {
            for ($index = 1; $index <= $chunkCount; $index++) {
                $chunkFilename = sprintf('%s.part%05d', $prefix, $index);
                $chunkPath = $this->writeLocalChunk($source, $chunkSize, $chunkFilename);

                try {
                    try {
                        $this->bitrixAdminClient->uploadFile(
                            $endpoint,
                            $sessionId,
                            $chunkPath,
                            $remoteDirectory,
                            $chunkFilename,
                        );
                    } catch (\RuntimeException $err) {
                        if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                            throw $err;
                        }

                        $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
                        $this->bitrixAdminClient->uploadFile(
                            $endpoint,
                            $sessionId,
                            $chunkPath,
                            $remoteDirectory,
                            $chunkFilename,
                        );
                    }
                } finally {
                    @unlink($chunkPath);
                }

                $remoteChunks[] = rtrim($remoteDirectory, '/') . '/' . $chunkFilename;
                $sent = min($size, $index * $chunkSize);
                $percent = $size === 0 ? 100 : (int) floor($sent * 100 / $size);
                $this->printer->info(sprintf(
                    'Отправлена часть %d/%d (%d байт, %d%%).',
                    $index,
                    $chunkCount,
                    $sent,
                    $percent,
                ));
            }
        } finally {
            fclose($source);
        }

        return $this->mergeRemoteChunks(
            $codename,
            $config,
            $endpoint,
            $sessionId,
            $remoteChunks,
            rtrim($remoteDirectory, '/') . '/' . $filename,
        );
    }

    /** @param resource $source */
    protected function writeLocalChunk($source, int $chunkSize, string $chunkFilename): string
    {
        $chunkPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $chunkFilename;
        $target = fopen($chunkPath, 'wb');

        if ($target === false) {
            throw new \RuntimeException(
                sprintf('Не удалось создать временный файл: %s', $chunkPath),
                static::CODE_IO_ERROR,
            );
        }

        try {
            $remaining = $chunkSize;

            while ($remaining > 0 && !feof($source)) {
                $buffer = fread($source, min($remaining, 1024 * 1024));

                if ($buffer === false) {
                    throw new \RuntimeException('Не удалось прочитать очередную часть файла.', static::CODE_IO_ERROR);
                }

                if ($buffer === '') {
                    break;
                }

                if (fwrite($target, $buffer) === false) {
                    throw new \RuntimeException('Не удалось записать временную часть файла.', static::CODE_IO_ERROR);
                }

                $remaining -= strlen($buffer);
            }
        } finally {
            fclose($target);
        }

        return $chunkPath;
    }

    /**
     * @param mixed[] $config
     * @param string[] $chunks
     */
    protected function mergeRemoteChunks(
        string $codename,
        array $config,
        string $endpoint,
        string $sessionId,
        array $chunks,
        string $destination
    ): string {
        $code = $this->remoteFilePhpCodeBuilder->buildMergeChunks($destination, $chunks);

        try {
            $json = $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $json = $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        }

        $result = json_decode($json, true);

        if (!is_array($result) || ($result['ok'] ?? false) !== true) {
            $error = is_array($result) && is_string($result['error'] ?? null)
                ? $result['error']
                : 'Не удалось объединить части файла на удаленном проекте.';
            throw new \RuntimeException($error);
        }

        return $sessionId;
    }

    /**
     * @return array{
     *     upload_max_filesize: string,
     *     post_max_size: string,
     *     max_post_file_bytes: int
     * }
     */
    protected function loadRemoteUploadLimits(string $endpoint, string $sessionId): array
    {
        $json = $this->bitrixAdminClient->executePhp(
            $endpoint,
            $sessionId,
            $this->remoteFilePhpCodeBuilder->buildUploadLimits(),
        );
        $result = json_decode($json, true);

        if (!is_array($result) || ($result['ok'] ?? false) !== true) {
            $error = is_array($result) && is_string($result['error'] ?? null)
                ? $result['error']
                : 'Не удалось получить ограничения загрузки удаленного проекта.';
            throw new \RuntimeException($error);
        }

        return [
            'upload_max_filesize' => is_string($result['upload_max_filesize'] ?? null)
                ? $result['upload_max_filesize']
                : '',
            'post_max_size' => is_string($result['post_max_size'] ?? null) ? $result['post_max_size'] : '',
            'max_post_file_bytes' => (int) ($result['max_post_file_bytes'] ?? 0),
        ];
    }

    protected function deleteRemoteFile(string $endpoint, string $sessionId, string $path): void
    {
        try {
            $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, $path);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() === 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }
        }
    }

    protected function resolveSourcePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new \RuntimeException('Путь src не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $absolutePath = $path[0] === '/' ? $path : getcwd() . '/' . $path;
        $realPath = realpath($absolutePath);

        if ($realPath === false || !is_file($realPath)) {
            throw new \RuntimeException(sprintf('Локальный файл не найден: %s', $path), static::CODE_IO_ERROR);
        }

        if (!is_readable($realPath)) {
            throw new \RuntimeException(
                sprintf('Локальный файл недоступен для чтения: %s', $path),
                static::CODE_IO_ERROR,
            );
        }

        return $realPath;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveRemoteDestination(string $path, string $sourceFilename): array
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new \RuntimeException('Путь dest не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $isDirectoryTarget = str_ends_with($path, '/');
        $normalizedPath = $this->normalizeRemotePath($path);

        if ($isDirectoryTarget) {
            return [$normalizedPath, $sourceFilename];
        }

        $directory = dirname($normalizedPath);
        $filename = basename($normalizedPath);

        if ($directory === '/' && $filename === '') {
            throw new \RuntimeException(
                'Путь dest должен указывать на файл или директорию.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        return [$directory === '.' ? '/' : $directory, $filename];
    }

    protected function normalizeRemotePath(string $path): string
    {
        $segments = [];

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(
                    'Путь dest не должен выходить за document root.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return '/';
        }

        return '/' . implode('/', $segments);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['байт', 'КБ', 'МБ', 'ГБ'];
        $value = (float) $bytes;

        foreach ($units as $index => $unit) {
            if ($value < 1024 || $index === count($units) - 1) {
                return $index === 0
                    ? $bytes . ' ' . $unit
                    : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . ' ' . $unit;
            }

            $value /= 1024;
        }

        return $bytes . ' байт';
    }
}
