<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\File;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemoteFilePhpCodeBuilder;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetCommand extends BxCommand
{
    protected static $defaultName = 'file:get';

    private const PROGRESS_BAR_THRESHOLD = 1048576;

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
            ->setDescription('Скачивает файл из файловой структуры проекта')
            ->setHelp('Команда копирует файл относительно document root локального или удаленного проекта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption(
                'compress',
                null,
                InputOption::VALUE_REQUIRED,
                'Сжать файл или папку перед скачиванием (доступно: zip)'
            )
            ->addArgument('src', InputArgument::REQUIRED, 'Путь к файлу относительно document root проекта')
            ->addArgument('dest', InputArgument::REQUIRED, 'Локальный путь назначения');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $srcArgument = $input->getArgument('src');
        $destArgument = $input->getArgument('dest');
        $compress = $input->getOption('compress');

        if (!is_string($srcArgument) || !is_string($destArgument)) {
            throw new \RuntimeException(
                'Аргументы src и dest должны быть строками.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $src = $this->normalizeSourcePath($srcArgument);
        $compressType = is_string($compress) ? $this->normalizeCompressType($compress) : null;
        $dest = $this->resolveDestinationPath($destArgument, $this->getDestinationSourceName($src, $compressType));

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($remote, $src, $dest, $output, $compressType);
            return;
        }

        parent::executeInternal($input, $output);
        if ($compressType !== null) {
            throw new \RuntimeException(
                'Опция --compress поддерживается только для удаленных проектов.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $this->executeLocal($src, $dest);
    }

    protected function executeLocal(string $src, string $dest): void
    {
        $sourcePath = $this->resolveLocalSourcePath($src);
        $this->ensureDestinationDirectory($dest);

        if (!is_file($sourcePath)) {
            throw new \RuntimeException(sprintf('Файл не найден: %s', $src), static::CODE_IO_ERROR);
        }

        $this->ensureDestinationDoesNotExist($dest);

        if (!copy($sourcePath, $dest)) {
            throw new \RuntimeException(sprintf('Не удалось скопировать файл в: %s', $dest), static::CODE_IO_ERROR);
        }

        $this->printer->info(sprintf('Файл сохранен: %s', $dest));
    }

    protected function executeRemote(
        string $codename,
        string $src,
        string $dest,
        OutputInterface $output,
        ?string $compressType = null
    ): void {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        $progressBar = null;
        $progressFactory = function (int $contentLength) use ($output, &$progressBar): ?callable {
            if ($contentLength <= self::PROGRESS_BAR_THRESHOLD) {
                return null;
            }

            $progressBar = new ProgressBar($output, $contentLength);
            $progressBar->setFormat(' %current%/%max% байт [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
            $progressBar->start();

            return static function (int $downloaded) use ($progressBar): void {
                $progressBar->setProgress($downloaded);
            };
        };

        $this->ensureDestinationDirectory($dest);
        $this->ensureDestinationDoesNotExist($dest);

        $downloadSrc = $src;
        if ($compressType !== null) {
            [$sessionId, $downloadSrc] = $this->compressRemoteSource(
                $codename,
                $config,
                $endpoint,
                $sessionId,
                $src,
                $compressType,
            );
        }

        try {
            $this->bitrixAdminClient->downloadFile($endpoint, $sessionId, $downloadSrc, $dest, $progressFactory);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $this->bitrixAdminClient->downloadFile($endpoint, $sessionId, $downloadSrc, $dest, $progressFactory);
        } finally {
            if ($compressType !== null) {
                $this->deleteRemoteTemporaryFile($codename, $config, $endpoint, $sessionId, $downloadSrc);
            }

            if ($progressBar !== null) {
                $progressBar->finish();
                $output->writeln('');
            }
        }

        $this->printer->info(sprintf('Файл сохранен: %s', $dest));
    }


    protected function normalizeCompressType(string $type): string
    {
        $type = strtolower(trim($type));

        if ($type !== 'zip') {
            throw new \RuntimeException('Поддерживаемый тип сжатия: zip.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return $type;
    }

    protected function getDestinationSourceName(string $src, ?string $compressType): string
    {
        if ($compressType === null) {
            return $src;
        }

        return rtrim($src, '/') . '.' . $compressType;
    }

    /**
     * @param mixed[] $config
     * @return array{string, string}
     */
    protected function compressRemoteSource(
        string $codename,
        array $config,
        string $endpoint,
        string $sessionId,
        string $src,
        string $compressType
    ): array {
        $code = $this->remoteFilePhpCodeBuilder->buildCompress($src, $compressType);

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
        if (!is_array($result) || ($result['ok'] ?? false) !== true || !is_string($result['path'] ?? null)) {
            $error = is_array($result) && is_string($result['error'] ?? null)
                ? $result['error']
                : 'Не удалось сжать файл или папку на удаленном проекте.';
            throw new \RuntimeException($error);
        }

        return [$sessionId, $result['path']];
    }

    /** @param mixed[] $config */
    protected function deleteRemoteTemporaryFile(
        string $codename,
        array $config,
        string $endpoint,
        string $sessionId,
        string $path
    ): void {
        $code = $this->remoteFilePhpCodeBuilder->buildDelete($path);

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
                : 'Не удалось удалить временный архив на удаленном проекте.';
            throw new \RuntimeException($error);
        }
    }

    protected function normalizeSourcePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new \RuntimeException('Путь src не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $segments = [];
        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(
                    'Путь src не должен выходить за document root.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \RuntimeException('Путь src должен указывать на файл.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return '/' . implode('/', $segments);
    }

    protected function resolveDestinationPath(string $path, string $src): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new \RuntimeException('Путь dest не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $isDirectoryTarget = is_dir($path) || str_ends_with($path, '/') || str_ends_with($path, '\\');
        $absolutePath = $this->absolutizePath($path);

        if ($isDirectoryTarget) {
            $absolutePath = rtrim($absolutePath, '/') . '/' . basename($src);
        }

        return $this->normalizeLocalPath($absolutePath);
    }

    protected function absolutizePath(string $path): string
    {
        if ($path[0] === '/') {
            return $path;
        }

        return getcwd() . '/' . $path;
    }

    protected function normalizeLocalPath(string $path): string
    {
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }

    protected function resolveLocalSourcePath(string $src): string
    {
        $documentRoot = rtrim((string) $this->getDocumentRoot(), '/');

        return $documentRoot . $src;
    }

    protected function ensureDestinationDirectory(string $dest): void
    {
        $dir = dirname($dest);

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(
                sprintf('Не удалось создать каталог назначения: %s', $dir),
                static::CODE_IO_ERROR,
            );
        }
    }

    protected function ensureDestinationDoesNotExist(string $dest): void
    {
        if (file_exists($dest)) {
            throw new \RuntimeException(
                sprintf('Файл назначения уже существует: %s', $dest),
                static::CODE_IO_ERROR,
            );
        }
    }
}
