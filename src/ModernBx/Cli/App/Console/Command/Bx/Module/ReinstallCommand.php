<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Module;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Console\Mixin\Bx\ModuleLifecycle;
use ModernBx\Cli\App\Console\Mixin\Bx\ModuleLifecycleWarningCode;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReinstallCommand extends KernelCommand
{
    use ModuleLifecycle;

    /**
     * @var string
     */
    protected static $defaultName = 'module:reinstall';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.module_reinstall.description"))
            ->setHelp($this->trans("command.module_reinstall.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'module',
                        InputArgument::REQUIRED,
                        $this->trans("argument.module.code"),
                    ),
                ])
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        $moduleCode = $this->getModuleCode($input->getArgument("module"));

        $uninstallResult = $this->uninstallModule($moduleCode);

        $uninstallWarnings = $uninstallResult->getWarnings(ModuleLifecycleWarningCode::MODULE_NOT_INSTALLED);

        if ($uninstallWarnings) {
            $this->printer->put($uninstallWarnings[0]->message, "comment");
        } else {
            $this->printer->info($this->trans("message.module.uninstalled", ["%module%" => $moduleCode]));
        }

        $installResult = $this->installModule($moduleCode);

        $installWarnings = $installResult->getWarnings(ModuleLifecycleWarningCode::MODULE_ALREADY_INSTALLED);

        if ($installWarnings) {
            $this->printer->put($installWarnings[0]->message, "comment");

            return;
        }

        $this->printer->info($this->trans("message.module.installed", ["%module%" => $moduleCode]));
    }
}
