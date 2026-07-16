<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\Common\IO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OptionSetCommand extends KernelCommand
{
    use IO;

    /**
     * @var string
     */
    protected static $defaultName = 'option:set';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.option_set.description"))
            ->setHelp($this->trans("command.option_set.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'option',
                        InputArgument::REQUIRED,
                        $this->trans("argument.option.name"),
                    ),
                    new InputArgument(
                        'value',
                        InputArgument::REQUIRED,
                        $this->trans("argument.option.value"),
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

        /** @var string $option */
        $option = $input->getArgument("option");
        /** @var string $value */
        $value = $input->getArgument("value");
        [$moduleName, $optionName, $siteId] = explode(".", $option);

        /** @noinspection PhpUndefinedClassInspection */
        /** @noinspection PhpUndefinedNamespaceInspection */
        /** @var string $optionValue */
        /** @phpstan-ignore-next-line */
        \Bitrix\Main\Config\Option::set(
            $moduleName,
            $optionName,
            $value,
            $siteId ?: "",
        );
    }
}
