<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\IO;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteListCommand extends KernelCommand
{
    use IO;

    /**
     * @var string
     */
    protected static $defaultName = 'site:list';

    protected function configure(): void
    {
        $this
            ->setDescription("List ID of Bitrix sites")
            ->setHelp("List ID of Bitrix sites, one ID per line");
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

        //** @noinspection PhpUndefinedClassInspection */
        /** @var string $version */
        /** @phpstan-ignore-next-line */
        $cursor = \CSite::GetList();

        while ($site = $cursor->Fetch()) {
            $this->printer->info($site["ID"]);
        }
    }
}
