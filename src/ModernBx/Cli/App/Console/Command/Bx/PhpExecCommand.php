<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PhpExecCommand extends KernelCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'php:exec';

    protected RemoteProjectConfigManager $remoteProjectConfigManager;

    protected BitrixAdminClient $bitrixAdminClient;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient
    ) {
        parent::__construct($aliasLoader);

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.php_exec.description'))
            ->setHelp($this->trans('command.php_exec.help'))
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($remote);
            return;
        }

        parent::executeInternal($input, $output);
        $this->executeLocal();
    }

    protected function executeLocal(): void
    {
        $code = (string) stream_get_contents(STDIN);
        $file = tempnam(sys_get_temp_dir(), 'modern-bx-php-exec-');

        if ($file === false) {
            throw new \Exception('Unable to create temporary PHP file.');
        }

        try {
            file_put_contents($file, "<?php
" . $code);
            require $file;
        } finally {
            unlink($file);
        }
    }

    protected function executeRemote(string $codename): void
    {
        $code = (string) stream_get_contents(STDIN);
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        try {
            $result = $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $result = $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        }

        $this->printer->info($result);
    }
}
