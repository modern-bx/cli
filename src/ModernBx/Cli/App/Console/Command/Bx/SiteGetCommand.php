<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\IO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\to_json;

class SiteGetCommand extends KernelCommand
{
    use IO;

    /**
     * @var string
     */
    protected static $defaultName = 'site:get';

    protected function configure(): void
    {
        $this
            ->setDescription("Print Bitrix site fields")
            ->setHelp("Print Bitrix site fields as JSON")
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'id',
                        InputArgument::REQUIRED,
                        "Site ID",
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
        /** @var array<mixed>|null $site */
        /** @phpstan-ignore-next-line */
        $site = \CSite::GetByID($input->getArgument("id"))->Fetch();

        if (!$site) {
            return;
        }

        $this->printer->info((string) to_json($site));
    }
}
