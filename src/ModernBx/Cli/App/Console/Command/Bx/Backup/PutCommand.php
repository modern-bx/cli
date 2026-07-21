<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteBackupPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PutCommand extends BxCommand
{
    use RemotePhpTrait;

    /** @var string */
    protected static $defaultName = 'backup:put';

    protected RemoteBackupPhpCodeBuilder $remoteBackupPhpCodeBuilder;

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteBackupPhpCodeBuilder $remoteBackupPhpCodeBuilder
    ) {
        parent::__construct();

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteBackupPhpCodeBuilder = $remoteBackupPhpCodeBuilder;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Загружает локальную резервную копию в /bitrix/backup')
            ->setHelp('Загружает основной файл резервной копии и все найденные номерные тома в /bitrix/backup.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Игнорировать пропуски томов и перезаписывать файлы')
            ->addArgument('src', InputArgument::REQUIRED, 'Путь к локальному основному файлу резервной копии');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $srcArgument = $input->getArgument('src');

        if (!is_string($srcArgument)) {
            throw new \RuntimeException('Аргумент src должен быть строкой.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $sourcePath = $this->resolveSourcePath($srcArgument);
        $force = $input->getOption('force') === true;
        $sourcePaths = $this->findBackupVolumePaths($sourcePath, $force);

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($remote, $sourcePaths, $force);
            return;
        }

        parent::executeInternal($input, $output);
        $this->executeLocal($sourcePaths, $force);
    }

    /** @param list<string> $sourcePaths */
    protected function executeRemote(string $codename, array $sourcePaths, bool $force): void
    {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        $totalBytes = 0;

        foreach ($sourcePaths as $sourcePath) {
            $filename = basename($sourcePath);
            $remotePath = '/bitrix/backup/' . $filename;

            $exists = $this->remoteBackupFileExists($codename, $filename);

            if ($exists && !$force) {
                throw new \RuntimeException(
                    sprintf('Файл уже существует: %s', $remotePath),
                    static::CODE_IO_ERROR,
                );
            }

            if ($exists) {
                try {
                    $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, $remotePath);
                } catch (\RuntimeException $err) {
                    if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                        throw $err;
                    }

                    $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
                    $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, $remotePath);
                }
            }

            try {
                $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $sourcePath, '/bitrix/backup', $filename);
            } catch (\RuntimeException $err) {
                if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                    throw $err;
                }

                $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
                $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $sourcePath, '/bitrix/backup', $filename);
            }

            $bytes = $this->readFileSize($sourcePath);
            $totalBytes += $bytes;
            $this->printProgress($filename, $bytes, $totalBytes);
        }
    }

    /** @param list<string> $sourcePaths */
    protected function executeLocal(array $sourcePaths, bool $force): void
    {
        $documentRoot = rtrim($this->getDocumentRoot()->toString(), '/');
        $backupDirectory = $documentRoot . '/bitrix/backup';

        if (!is_dir($backupDirectory)) {
            throw new \RuntimeException('Директория резервных копий не найдена: /bitrix/backup', static::CODE_IO_ERROR);
        }

        $totalBytes = 0;

        foreach ($sourcePaths as $sourcePath) {
            $destinationPath = $backupDirectory . '/' . basename($sourcePath);

            if (file_exists($destinationPath) && !$force) {
                throw new \RuntimeException(
                    sprintf('Файл уже существует: %s', $destinationPath),
                    static::CODE_IO_ERROR,
                );
            }

            $bytes = $this->copyVolume($sourcePath, $destinationPath);
            $totalBytes += $bytes;
            $this->printProgress(basename($sourcePath), $bytes, $totalBytes);
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
            throw new \RuntimeException(
                sprintf('Основной файл резервной копии не найден: %s', $path),
                static::CODE_IO_ERROR,
            );
        }

        if (!is_readable($realPath)) {
            throw new \RuntimeException(sprintf('Файл недоступен для чтения: %s', $path), static::CODE_IO_ERROR);
        }

        if (preg_match('/\.gz$/', basename($realPath)) !== 1) {
            throw new \RuntimeException(
                'Основной файл резервной копии должен иметь расширение .gz.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        return $realPath;
    }

    /** @return list<string> */
    protected function findBackupVolumePaths(string $mainPath, bool $force): array
    {
        $volumePaths = glob($mainPath . '.*');

        if ($volumePaths === false) {
            throw new \RuntimeException(
                sprintf('Не удалось найти тома резервной копии: %s', basename($mainPath)),
                static::CODE_IO_ERROR,
            );
        }

        $volumes = [];

        foreach ($volumePaths as $path) {
            $suffix = substr($path, strlen($mainPath) + 1);

            if (!ctype_digit($suffix) || (int) $suffix <= 0 || !is_file($path)) {
                continue;
            }

            $volumes[(int) $suffix] = $path;
        }

        ksort($volumes, SORT_NUMERIC);
        $missingVolume = $this->findFirstMissingVolume(array_keys($volumes));

        if ($missingVolume !== null && !$force) {
            throw new \RuntimeException(
                sprintf('В резервной копии пропущен том с номером %d.', $missingVolume),
                static::CODE_IO_ERROR,
            );
        }

        return array_merge([$mainPath], array_values($volumes));
    }

    /** @param list<int> $volumes */
    protected function findFirstMissingVolume(array $volumes): ?int
    {
        foreach ($volumes as $index => $volume) {
            $expected = $index + 1;

            if ($volume !== $expected) {
                return $expected;
            }
        }

        return null;
    }

    protected function remoteBackupFileExists(string $codename, string $filename): bool
    {
        $result = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($codename, $this->remoteBackupPhpCodeBuilder->buildExists($filename)),
            'Не удалось проверить наличие файла резервной копии на удаленном проекте.',
        );

        return $result === true;
    }

    protected function copyVolume(string $sourcePath, string $destinationPath): int
    {
        if (!copy($sourcePath, $destinationPath)) {
            throw new \RuntimeException(
                sprintf('Не удалось скопировать том %s в %s.', basename($sourcePath), $destinationPath),
                static::CODE_IO_ERROR,
            );
        }

        return $this->readFileSize($destinationPath);
    }

    protected function readFileSize(string $path): int
    {
        $size = filesize($path);

        if ($size === false) {
            throw new \RuntimeException(
                sprintf('Не удалось определить размер тома: %s', basename($path)),
                static::CODE_IO_ERROR,
            );
        }

        return $size;
    }

    protected function printProgress(string $name, int $bytes, int $totalBytes): void
    {
        $this->printer->info(sprintf(
            'Загружен том %s: %s, всего %s.',
            $name,
            $this->formatBytes($bytes),
            $this->formatBytes($totalBytes),
        ));
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            ++$unitIndex;
        }

        if ($unitIndex === 0) {
            return sprintf('%d %s', $bytes, $units[$unitIndex]);
        }

        return sprintf('%.2f %s', $value, $units[$unitIndex]);
    }
}
