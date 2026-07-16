<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\Common\IO;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\to_json;

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
            ->setDescription("List Bitrix sites")
            ->setHelp("Print Bitrix sites as JSON rows using D7 SiteTable::getList")
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'filter',
                        null,
                        InputOption::VALUE_REQUIRED,
                        "JSON for SiteTable::getList filter parameter",
                    ),
                    new InputOption(
                        'order',
                        null,
                        InputOption::VALUE_REQUIRED,
                        "JSON for SiteTable::getList order parameter",
                    ),
                    new InputOption(
                        'select',
                        null,
                        InputOption::VALUE_REQUIRED,
                        "JSON for SiteTable::getList select parameter",
                    ),
                    new InputOption(
                        'pretty',
                        null,
                        InputOption::VALUE_NONE,
                        "Pretty-print JSON output",
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

        $query = [];

        foreach (["filter", "order", "select"] as $option) {
            $value = $input->getOption($option);

            if ($value === null) {
                continue;
            }

            if (!is_string($value)) {
                throw new \Exception(
                    "Option --" . $option . " must be a JSON string.",
                    static::CODE_INVALID_OPTION_VALUE
                );
            }

            $query[$option] = $this->decodeJsonOption($option, $value);
        }

        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        /** @noinspection PhpUndefinedClassInspection */
        /** @phpstan-ignore-next-line */
        $cursor = \Bitrix\Main\SiteTable::getList($query);

        while ($site = $cursor->fetch()) {
            $this->printer->info((string) to_json($site, $flags));
        }
    }

    /**
     * @param string $option
     * @param string $value
     * @return mixed
     * @throws \Exception
     */
    private function decodeJsonOption(string $option, string $value): mixed
    {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                "Option --" . $option . " contains invalid JSON: " . json_last_error_msg(),
                static::CODE_INVALID_OPTION_VALUE
            );
        }

        return $decoded;
    }
}
