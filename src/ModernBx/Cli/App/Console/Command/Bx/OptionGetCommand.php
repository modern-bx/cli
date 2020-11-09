<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\IO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\to_json;

class OptionGetCommand extends KernelCommand
{
    use IO;

    /**
     * @var string
     */
    protected static $defaultName = 'option:get';

    protected function configure(): void
    {
        $this
            ->setDescription("Get Bitrix module option value")
            ->setHelp("Print value of the Bitrix module option. Multiple option names are allowed")
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'unserialize',
                        'u',
                        InputOption::VALUE_NONE,
                        "Unserialize option value, if possible",
                    ),
                    new InputArgument(
                        'option',
                        InputArgument::IS_ARRAY,
                        "List of module option codes. Format: module.option[.lid]",
                    ),
                ]),
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

        /** @var array<string> $optionList */
        $optionList = $input->getArgument("option");

        foreach ($optionList as $option) {
            [$moduleName, $optionName, $siteId] = explode(".", $option);

            /** @noinspection PhpUndefinedClassInspection */
            /** @noinspection PhpUndefinedNamespaceInspection */
            /** @var string $optionValue */
            /** @phpstan-ignore-next-line */
            $optionValue = \Bitrix\Main\Config\Option::get(
                $moduleName,
                $optionName,
                "",
                $siteId ?: false,
            );

            if ($input->getOption("unserialize")) {
                $unserializedValue = @unserialize($optionValue);
            }

            $this->printer->info((string) to_json($unserializedValue ?? $optionValue));
        }
    }
}
