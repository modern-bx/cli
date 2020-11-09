<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\IO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteSetCommand extends KernelCommand
{
    use IO;

    /**
     * @var string
     */
    protected static $defaultName = 'site:set';

    protected function configure(): void
    {
        $this
            ->setDescription("Set Bitrix site field value")
            ->setHelp("Set Bitrix site field value")
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'id',
                        InputArgument::REQUIRED,
                        "Site ID",
                    ),
                    new InputArgument(
                        'field',
                        InputArgument::REQUIRED,
                        "Field",
                    ),
                    new InputArgument(
                        'value',
                        InputArgument::REQUIRED,
                        "Field value",
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

        /** @noinspection PhpUndefinedClassInspection */
        /** @var string $version */
        /** @phpstan-ignore-next-line */
        $site = new \CSite();
        /** @phpstan-ignore-next-line */
        $site->Update($input->getArgument("id"), [
            $input->getArgument("field") => $input->getArgument("value"),
        ]);

        /** @phpstan-ignore-next-line */
        if ($site->LAST_ERROR) {
            /** @phpstan-ignore-next-line */
            $this->printer->error($site->LAST_ERROR);
        }
    }
}
