<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\File;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteFilePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractCommand extends BxCommand
{
    protected static $defaultName = 'file:extract';

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
            ->setDescription('Распаковывает архив в файловой структуре проекта')
            ->setHelp('Распаковывает архив по пути относительно document root в папку проекта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Формат архива (доступно: zip)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Перезаписывать существующие файлы')
            ->addArgument('src', InputArgument::REQUIRED, 'Путь к архиву относительно document root проекта')
            ->addArgument(
                'dest',
                InputArgument::REQUIRED,
                'Путь к папке назначения относительно document root проекта'
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $srcArgument = $input->getArgument('src');
        $destArgument = $input->getArgument('dest');
        $formatOption = $input->getOption('format');
        $force = (bool) $input->getOption('force');

        if (!is_string($srcArgument) || !is_string($destArgument)) {
            throw new \RuntimeException(
                'Аргументы src и dest должны быть строками.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $src = $this->normalizeProjectPath($srcArgument, 'Путь src');
        $dest = $this->normalizeProjectPath($destArgument, 'Путь dest');
        $format = is_string($formatOption)
            ? $this->normalizeFormat($formatOption)
            : $this->detectFormat($src);

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($remote, $src, $dest, $format, $force);
            return;
        }

        parent::executeInternal($input, $output);
        $this->executeLocal($src, $dest, $format, $force);
    }

    protected function executeLocal(string $src, string $dest, string $format, bool $force): void
    {
        if ($format !== 'zip') {
            throw new \RuntimeException('Поддерживаемый формат архива: zip.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('PHP-расширение ZipArchive недоступно.', static::CODE_IO_ERROR);
        }

        $documentRoot = rtrim((string) $this->getDocumentRoot(), '/');
        $sourcePath = $documentRoot . $src;
        $destinationPath = $documentRoot . $dest;

        if (!is_file($sourcePath)) {
            throw new \RuntimeException(sprintf('Архив не найден: %s', $src), static::CODE_IO_ERROR);
        }

        if (file_exists($destinationPath) && !is_dir($destinationPath)) {
            throw new \RuntimeException(
                sprintf('Путь назначения существует и не является папкой: %s', $dest),
                static::CODE_IO_ERROR,
            );
        }

        if (!is_dir($destinationPath) && !mkdir($destinationPath, 0775, true) && !is_dir($destinationPath)) {
            throw new \RuntimeException(
                sprintf('Не удалось создать папку назначения: %s', $dest),
                static::CODE_IO_ERROR,
            );
        }

        $zip = new \ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            throw new \RuntimeException(sprintf('Не удалось открыть zip-архив: %s', $src), static::CODE_IO_ERROR);
        }

        try {
            $notices = $this->validateZipArchive($zip, $destinationPath, $dest, $force);

            if (!$zip->extractTo($destinationPath)) {
                throw new \RuntimeException(
                    sprintf('Не удалось распаковать zip-архив: %s', $src),
                    static::CODE_IO_ERROR,
                );
            }
        } finally {
            $zip->close();
        }

        $this->printNotices($notices);
        $this->printer->info(sprintf('Архив распакован: %s -> %s', $src, $dest));
    }

    protected function executeRemote(string $codename, string $src, string $dest, string $format, bool $force): void
    {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        $code = $this->remoteFilePhpCodeBuilder->buildExtract($src, $dest, $format, $force);

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
                : 'Не удалось распаковать архив на удаленном проекте.';
            throw new \RuntimeException($error);
        }

        $notices = is_array($result['notices'] ?? null) ? $result['notices'] : [];
        $this->printNotices(array_values(array_filter($notices, 'is_string')));
        $this->printer->info(sprintf('Архив распакован: %s -> %s', $src, $dest));
    }

    protected function normalizeProjectPath(string $path, string $name): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new \RuntimeException($name . ' не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $segments = [];
        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(
                    $name . ' не должен выходить за document root.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \RuntimeException(
                $name . ' должен указывать на вложенный путь.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        return '/' . implode('/', $segments);
    }

    protected function detectFormat(string $src): string
    {
        $extension = strtolower(pathinfo($src, PATHINFO_EXTENSION));

        if ($extension === 'zip') {
            return 'zip';
        }

        throw new \RuntimeException(
            'Не удалось определить формат архива по расширению. Укажите --format=zip.',
            static::CODE_INVALID_ARGUMENT_VALUE,
        );
    }

    protected function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if ($format !== 'zip') {
            throw new \RuntimeException('Поддерживаемый формат архива: zip.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return $format;
    }

    /** @return string[] */
    protected function validateZipArchive(\ZipArchive $zip, string $destinationPath, string $dest, bool $force): array
    {
        $notices = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name) || $name === '') {
                throw new \RuntimeException('Zip-архив содержит запись без имени.', static::CODE_IO_ERROR);
            }

            $relativePath = $this->normalizeArchivePath($name);
            if ($relativePath === '') {
                continue;
            }

            $isDirectoryEntry = str_ends_with(str_replace('\\', '/', $name), '/');
            $targetPath = rtrim($destinationPath, '/') . '/' . $relativePath;
            $projectPath = rtrim($dest, '/') . '/' . $relativePath;

            if (!$isDirectoryEntry && is_file($targetPath)) {
                if (!$force) {
                    throw new \RuntimeException('Файл уже существует: ' . $projectPath, static::CODE_IO_ERROR);
                }

                $notices[] = 'Файл будет перезаписан: ' . $projectPath;
                continue;
            }

            if (!$isDirectoryEntry && is_dir($targetPath)) {
                throw new \RuntimeException(
                    'Нельзя распаковать файл поверх папки: ' . $projectPath,
                    static::CODE_IO_ERROR,
                );
            }

            if ($isDirectoryEntry && is_file($targetPath)) {
                throw new \RuntimeException(
                    'Нельзя создать папку поверх файла: ' . $projectPath,
                    static::CODE_IO_ERROR,
                );
            }
        }

        return $notices;
    }

    protected function normalizeArchivePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(
                    'Архив содержит путь, выходящий за папку распаковки: ' . $path,
                    static::CODE_IO_ERROR,
                );
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /** @param string[] $notices */
    protected function printNotices(array $notices): void
    {
        if (!$this->verbose) {
            return;
        }

        foreach ($notices as $notice) {
            $this->printer->info('Notice: ' . $notice);
        }
    }
}
