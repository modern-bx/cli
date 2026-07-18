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

class InstallCommand extends KernelCommand
{
    use ModuleLifecycle;

    /**
     * @var string
     */
    protected static $defaultName = 'module:install';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.module_install.description"))
            ->setHelp($this->trans("command.module_install.help"))
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

        $result = $this->installModule($this->getModuleCode($input->getArgument("module")));

        $moduleCode = $result->getModuleCode();

        $warnings = $result->getWarnings(ModuleLifecycleWarningCode::MODULE_ALREADY_INSTALLED);

        if ($warnings) {
            $this->printer->put($warnings[0]->message, "comment");

            return;
        }

        $this->printer->info($this->trans("message.module.installed", ["%module%" => $moduleCode]));
    }
}
