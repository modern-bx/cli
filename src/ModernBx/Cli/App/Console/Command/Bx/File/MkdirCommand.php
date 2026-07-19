<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\File;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MkdirCommand extends BxCommand
{
    protected static $defaultName = 'file:mkdir';

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
            ->setDescription('Создает директорию в файловой структуре проекта')
            ->setHelp('Создает директорию по пути относительно document root локального или удаленного проекта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addArgument(
                'directory-path',
                InputArgument::REQUIRED,
                'Путь к директории относительно document root проекта',
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $pathArgument = $input->getArgument('directory-path');

        if (!is_string($pathArgument)) {
            throw new \RuntimeException(
                'Аргумент directory-path должен быть строкой.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $path = $this->normalizeDirectoryPath($pathArgument);

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($remote, $path);
            return;
        }

        parent::executeInternal($input, $output);
        $this->executeLocal($path);
    }

    protected function executeLocal(string $path): void
    {
        $directory = rtrim((string) $this->getDocumentRoot(), '/') . $path;

        if (file_exists($directory)) {
            if (is_dir($directory)) {
                $this->printer->info(sprintf('Директория уже существует: %s', $path));
                return;
            }

            throw new \RuntimeException(
                sprintf('По указанному пути уже существует файл: %s', $path),
                static::CODE_IO_ERROR,
            );
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Не удалось создать директорию: %s', $path), static::CODE_IO_ERROR);
        }

        $this->printer->info(sprintf('Директория создана: %s', $path));
    }

    protected function executeRemote(string $codename, string $path): void
    {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        try {
            $this->bitrixAdminClient->createDirectory($endpoint, $sessionId, $path);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $this->bitrixAdminClient->createDirectory($endpoint, $sessionId, $path);
        }

        $this->printer->info(sprintf('Директория создана: %s', $path));
    }

    protected function normalizeDirectoryPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new \RuntimeException('Путь директории не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $segments = [];

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(
                    'Путь директории не должен выходить за document root.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \RuntimeException(
                'Путь директории должен указывать на вложенную директорию.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        return '/' . implode('/', $segments);
    }
}
