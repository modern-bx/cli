<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ModuleVersionCommand extends KernelCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'module:version';

    protected function configure(): void
    {
        $this
            ->setDescription("Get Bitrix module version")
            ->setHelp("Print version of the Bitrix module. Multiple module codes are allowed")
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'module',
                        InputArgument::IS_ARRAY,
                        "List of module vendor codes",
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

        /** @var array<string> $moduleList */
        $moduleList = $input->getArgument("module");

        foreach ($moduleList as $moduleCode) {
            /** @noinspection PhpUndefinedClassInspection */
            /** @noinspection PhpUndefinedNamespaceInspection */
            /** @var string $version */
            /** @phpstan-ignore-next-line */
            $version = \Bitrix\Main\ModuleManager::getVersion($moduleCode);

            $this->printer->info($version);
        }
    }
}
