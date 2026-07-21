<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\BxCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class GetCommand extends BxCommand
{
    /** @var string */
    protected static $defaultName = 'backup:get';

    protected function configure(): void
    {
        $this
            ->setDescription('Скачивает все тома резервной копии Bitrix')
            ->setHelp('Копирует основной файл резервной копии из /bitrix/backup и все найденные номерные тома.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Перезаписывать существующие файлы назначения')
            ->addArgument('backup', InputArgument::REQUIRED, 'Короткое имя основного файла резервной копии')
            ->addArgument('dest', InputArgument::REQUIRED, 'Локальная директория назначения');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        $backupArgument = $input->getArgument('backup');
        $destArgument = $input->getArgument('dest');

        if (!is_string($backupArgument) || !is_string($destArgument)) {
            throw new \RuntimeException(
                'Аргументы backup и dest должны быть строками.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $this->executeLocal(
            $this->normalizeBackupName($backupArgument),
            $destArgument,
            $input->getOption('force') === true,
        );
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

            $this->printer->info(sprintf(
                'Скачан том %s: %s, всего %s.',
                basename($sourcePath),
                $this->formatBytes($bytes),
                $this->formatBytes($totalBytes),
            ));
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

        $size = filesize($destinationPath);

        if ($size === false) {
            throw new \RuntimeException(
                sprintf('Не удалось определить размер скачанного тома: %s', basename($destinationPath)),
                static::CODE_IO_ERROR,
            );
        }

        return $size;
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
