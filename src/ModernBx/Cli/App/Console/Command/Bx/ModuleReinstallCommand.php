<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\Bx\ModuleLifecycle;
use ModernBx\Cli\App\Console\Mixin\Bx\ModuleLifecycleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ModuleReinstallCommand extends KernelCommand
{
    use ModuleLifecycle;
    use ModuleLifecycleOutput;

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

        $this->printModuleLifecycleResult($this->uninstallModule($moduleCode));
        $this->printModuleLifecycleResult($this->installModule($moduleCode));
    }
}
