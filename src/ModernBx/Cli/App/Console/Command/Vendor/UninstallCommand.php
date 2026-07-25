<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Vendor;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Vendor\InstallationPath;
use ModernBx\Cli\App\Service\Vendor\PackageStrategy;
use ModernBx\Cli\App\Service\Vendor\PackageStrategyRegistry;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class UninstallCommand extends BxCommand
{
    protected static $defaultName = 'vendor:uninstall';

    private RemoteProjectConfigManager $remoteProjectConfigManager;
    private BitrixAdminClient $bitrixAdminClient;
    private PackageStrategyRegistry $packageStrategyRegistry;

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        PackageStrategyRegistry $packageStrategyRegistry
    ) {
        parent::__construct();
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->packageStrategyRegistry = $packageStrategyRegistry;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Удаляет стороннее ПО из сайта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Папка установки относительно корня сайта')
            ->addArgument('package', InputArgument::REQUIRED, 'Пакет для удаления: adminer');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $package = $input->getArgument('package');
        if (!is_string($package)) {
            throw new \RuntimeException('Не указан пакет для удаления.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $strategy = $this->packageStrategyRegistry->get($package);
        $path = InstallationPath::resolveForUninstall($input->getOption('path'));
        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->uninstallRemote($remote, $path, $strategy);
        } else {
            parent::executeInternal($input, $output);
            $this->uninstallLocal($path, $strategy);
        }

        $this->printer->info(sprintf(
            '%s удален: %s/%s',
            ucfirst($strategy->getName()),
            $path,
            $strategy->getMainFilename(),
        ));
    }

    private function uninstallLocal(string $path, PackageStrategy $strategy): void
    {
        $documentRoot = $this->getDocumentRoot()->toString();
        if (!$strategy->isInstalledLocally($documentRoot, $path)) {
            throw new \RuntimeException('Пакет ' . $strategy->getName() . ' не установлен по указанному пути.');
        }

        $file = rtrim($documentRoot, '/') . $path . '/' . $strategy->getMainFilename();
        if (!unlink($file)) {
            throw new \RuntimeException('Не удалось удалить файл пакета: ' . $file, static::CODE_IO_ERROR);
        }
    }

    private function uninstallRemote(string $codename, string $path, PackageStrategy $strategy): void
    {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);
        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        try {
            $installed = $this->isInstalledRemote($endpoint, $sessionId, $strategy, $path);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $installed = $this->isInstalledRemote($endpoint, $sessionId, $strategy, $path);
        }

        if (!$installed) {
            throw new \RuntimeException('Пакет ' . $strategy->getName() . ' не установлен по указанному пути.');
        }

        $file = $path . '/' . $strategy->getMainFilename();
        try {
            $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, $file);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, $file);
        }
    }

    private function isInstalledRemote(
        string $endpoint,
        string $sessionId,
        PackageStrategy $strategy,
        string $path
    ): bool {
        $json = $this->bitrixAdminClient->executePhp(
            $endpoint,
            $sessionId,
            $strategy->buildRemoteInstalledCheck($path),
        );
        $result = json_decode($json, true);

        return is_array($result) && ($result['installed'] ?? false) === true;
    }
}
