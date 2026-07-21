<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class GetCommand extends BxCommand
{
    use RemotePhpTrait;

    /** @var string */
    protected static $defaultName = 'backup:get';

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient
    ) {
        parent::__construct();

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Скачивает все тома резервной копии Bitrix')
            ->setHelp('Копирует основной файл резервной копии из /bitrix/backup и все найденные номерные тома.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Перезаписывать существующие файлы назначения')
            ->addArgument('backup', InputArgument::REQUIRED, 'Короткое имя основного файла резервной копии')
            ->addArgument('dest', InputArgument::REQUIRED, 'Локальная директория назначения');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $backupArgument = $input->getArgument('backup');
        $destArgument = $input->getArgument('dest');

        if (!is_string($backupArgument) || !is_string($destArgument)) {
            throw new \RuntimeException(
                'Аргументы backup и dest должны быть строками.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $backupName = $this->normalizeBackupName($backupArgument);
        $force = $input->getOption('force') === true;

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($remote, $backupName, $destArgument, $force);
            return;
        }

        parent::executeInternal($input, $output);
        $this->executeLocal($backupName, $destArgument, $force);
    }


    protected function executeRemote(
        string $codename,
        string $backupName,
        string $destinationDirectory,
        bool $force
    ): void {
        $this->ensureDestinationDirectory($destinationDirectory);
        $remotePaths = $this->fetchRemoteVolumePaths($codename, $backupName);
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        $totalBytes = 0;

        foreach ($remotePaths as $remotePath) {
            $destinationPath = rtrim($destinationDirectory, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . basename($remotePath);

            if (file_exists($destinationPath) && !$force) {
                throw new \RuntimeException(
                    sprintf('Файл уже существует: %s', $destinationPath),
                    static::CODE_IO_ERROR,
                );
            }

            try {
                $this->bitrixAdminClient->downloadFile(
                    $endpoint,
                    $sessionId,
                    $remotePath,
                    $destinationPath,
                    static fn (int $contentLength): ?callable => null,
                );
            } catch (\RuntimeException $err) {
                if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                    throw $err;
                }

                $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
                $this->bitrixAdminClient->downloadFile(
                    $endpoint,
                    $sessionId,
                    $remotePath,
                    $destinationPath,
                    static fn (int $contentLength): ?callable => null,
                );
            }

            $bytes = $this->readFileSize($destinationPath);
            $totalBytes += $bytes;
            $this->printProgress(basename($remotePath), $bytes, $totalBytes);
        }
    }

    /** @return list<string> */
    protected function fetchRemoteVolumePaths(string $codename, string $backupName): array
    {
        $result = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($codename, $this->buildRemoteVolumeListCode($backupName)),
            'Не удалось получить список томов резервной копии удаленного проекта.',
        );

        if (!is_array($result)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный список томов.');
        }

        return array_values(array_filter($result, 'is_string'));
    }

    protected function buildRemoteVolumeListCode(string $backupName): string
    {
        return strtr(<<<'PHP'
$backupName = '__BACKUP_NAME__';
$send = static function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ok":false}';
};

try {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('DOCUMENT_ROOT не определен.');
    }

    $backupDirectory = $documentRoot . '/bitrix/backup';
    $mainPath = $backupDirectory . '/' . $backupName;

    if (!is_file($mainPath)) {
        throw new \RuntimeException('Основной файл резервной копии не найден: ' . $backupName);
    }

    $paths = ['/bitrix/backup/' . $backupName];
    $volumePaths = glob($mainPath . '.*') ?: [];
    $volumes = [];

    foreach ($volumePaths as $path) {
        $suffix = substr($path, strlen($mainPath) + 1);

        if (!ctype_digit($suffix) || (int) $suffix <= 0 || !is_file($path)) {
            continue;
        }

        $volumes[(int) $suffix] = '/bitrix/backup/' . basename($path);
    }

    ksort($volumes, SORT_NUMERIC);
    $send(['ok' => true, 'result' => array_merge($paths, array_values($volumes))]);
} catch (\Throwable $err) {
    $send(['ok' => false, 'error' => $err->getMessage()]);
}
PHP, [
            '__BACKUP_NAME__' => addslashes($backupName),
        ]);
    }

    protected function executeLocal(string $backupName, string $destinationDirectory, bool $force): void
    {
        $documentRoot = rtrim($this->getDocumentRoot()->toString(), '/');
        $backupDirectory = $documentRoot . '/bitrix/backup';

        if (!is_dir($backupDirectory)) {
            throw new \RuntimeException(
                'Директория резервных копий не найдена: /bitrix/backup',
                static::CODE_IO_ERROR,
            );
        }

        $this->ensureDestinationDirectory($destinationDirectory);
        $sourcePaths = $this->findBackupVolumePaths($backupDirectory, $backupName);
        $totalBytes = 0;

        foreach ($sourcePaths as $sourcePath) {
            $destinationPath = rtrim($destinationDirectory, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . basename($sourcePath);

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

    protected function normalizeBackupName(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name));

        if ($name === '') {
            throw new \RuntimeException(
                'Имя резервной копии не должно быть пустым.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        if (basename($name) !== $name) {
            throw new \RuntimeException(
                'Нужно указать короткое имя файла резервной копии без пути.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        if (preg_match('/\.gz$/', $name) !== 1) {
            throw new \RuntimeException(
                'Основной файл резервной копии должен иметь расширение .gz.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        return $name;
    }

    /** @return list<string> */
    protected function findBackupVolumePaths(string $backupDirectory, string $backupName): array
    {
        $mainPath = rtrim($backupDirectory, '/') . '/' . $backupName;

        if (!is_file($mainPath)) {
            throw new \RuntimeException(
                sprintf('Основной файл резервной копии не найден: %s', $backupName),
                static::CODE_IO_ERROR,
            );
        }

        $paths = [$mainPath];
        $volumePaths = glob($mainPath . '.*');

        if ($volumePaths === false) {
            throw new \RuntimeException(
                sprintf('Не удалось найти тома резервной копии: %s', $backupName),
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

        return array_merge($paths, array_values($volumes));
    }

    protected function ensureDestinationDirectory(string $destinationDirectory): void
    {
        if (is_dir($destinationDirectory)) {
            return;
        }

        if (file_exists($destinationDirectory)) {
            throw new \RuntimeException(
                sprintf('Путь назначения не является директорией: %s', $destinationDirectory),
                static::CODE_IO_ERROR,
            );
        }

        if (!mkdir($destinationDirectory, 0777, true) && !is_dir($destinationDirectory)) {
            throw new \RuntimeException(
                sprintf('Не удалось создать директорию назначения: %s', $destinationDirectory),
                static::CODE_IO_ERROR,
            );
        }
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
                sprintf('Не удалось определить размер скачанного тома: %s', basename($path)),
                static::CODE_IO_ERROR,
            );
        }

        return $size;
    }

    protected function printProgress(string $name, int $bytes, int $totalBytes): void
    {
        $this->printer->info(sprintf(
            'Скачан том %s: %s, всего %s.',
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
