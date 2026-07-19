<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Module;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Console\Mixin\Bx\ModuleLifecycle;
use ModernBx\Cli\App\Console\Mixin\Bx\ModuleLifecycleWarningCode;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteModulePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UninstallCommand extends KernelCommand
{
    use ModuleLifecycle;
    use RemotePhpTrait;

    private RemoteModulePhpCodeBuilder $remoteModulePhpCodeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteModulePhpCodeBuilder $remoteModulePhpCodeBuilder
    ) {
        parent::__construct($aliasLoader);
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteModulePhpCodeBuilder = $remoteModulePhpCodeBuilder;
    }

    /** @var string */
    protected static $defaultName = 'module:uninstall';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.module_uninstall.description"))
            ->setHelp($this->trans("command.module_uninstall.help"))
            ->setDefinition(new InputDefinition([
            new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
            new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
            new InputArgument('module', InputArgument::REQUIRED, $this->trans("argument.module.code")),
        ]));
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $moduleCode = $this->getModuleCode($input->getArgument("module"));
        $remote = $input->getOption('remote');
        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $result = $this->decodeRemoteJsonResult(
                $this->executeRemotePhp($remote, $this->remoteModulePhpCodeBuilder->buildUninstall($moduleCode)),
                'Не удалось удалить модуль удаленного проекта.'
            );
            $this->printUninstallResult(
                $moduleCode,
                is_array($result) && ($result['warning'] ?? null) === 'MODULE_NOT_INSTALLED'
            );
            return;
        }
        parent::executeInternal($input, $output);
        $result = $this->uninstallModule($moduleCode);
        $this->printUninstallResult(
            $result->getModuleCode(),
            (bool) $result->getWarnings(ModuleLifecycleWarningCode::MODULE_NOT_INSTALLED)
        );
    }

    protected function printUninstallResult(string $moduleCode, bool $notInstalled): void
    {
        if ($notInstalled) {
            $this->printer->put($this->trans("warning.module.not_installed", ["%module%" => $moduleCode]), "comment");
            return;
        }
        $this->printer->info($this->trans("message.module.uninstalled", ["%module%" => $moduleCode]));
    }
}
