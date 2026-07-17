<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\File;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCommand extends BxCommand
{
    protected static $defaultName = 'file:delete';

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
            ->setDescription('Удаляет файл из файловой структуры проекта')
            ->setHelp('Удаляет файл по пути относительно document root локального или удаленного проекта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addArgument('path', InputArgument::REQUIRED, 'Путь к файлу относительно document root проекта');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $pathArgument = $input->getArgument('path');

        if (!is_string($pathArgument)) {
            throw new \RuntimeException('Аргумент path должен быть строкой.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $path = $this->normalizeProjectPath($pathArgument);

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
        $file = rtrim((string) $this->getDocumentRoot(), '/') . $path;

        if (!file_exists($file)) {
            throw new \RuntimeException(sprintf('Файл не найден: %s', $path), static::CODE_IO_ERROR);
        }

        $deleted = is_dir($file) ? rmdir($file) : unlink($file);

        if (!$deleted) {
            throw new \RuntimeException(sprintf('Не удалось удалить файл: %s', $path), static::CODE_IO_ERROR);
        }

        $this->printer->info(sprintf('Файл удален: %s', $path));
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
            $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, $path);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, $path);
        }

        $this->printer->info(sprintf('Файл удален: %s', $path));
    }

    protected function normalizeProjectPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new \RuntimeException('Путь не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $segments = [];

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(
                    'Путь не должен выходить за document root.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \RuntimeException('Путь должен указывать на файл.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return '/' . implode('/', $segments);
    }
}
