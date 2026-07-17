<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\File;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
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
            ->setDescription('Загружает локальный файл в файловую структуру удаленного проекта')
            ->setHelp('Команда загружает локальный файл в путь относительно document root удаленного проекта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
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

        try {
            $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $src, $remoteDirectory, $filename);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $src, $remoteDirectory, $filename);
        }

        $this->printer->info(sprintf('Файл загружен: %s/%s', rtrim($remoteDirectory, '/'), $filename));
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
}
