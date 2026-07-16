<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\Common\IO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->setHelp("Print Bitrix site fields as JSON using D7 SiteTable::getList")
            ->setDefinition(
                new InputDefinition([
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
                    new InputArgument(
                        'id',
                        InputArgument::REQUIRED,
                        "Site ID",
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

        $siteId = $input->getArgument("id");

        if (!is_string($siteId)) {
            throw new \Exception("Site ID must be a string.", static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $query = [
            "filter" => ["=LID" => $siteId],
            "limit" => 1,
        ];
        $select = $input->getOption("select");

        if ($select !== null) {
            if (!is_string($select)) {
                throw new \Exception("Option --select must be a JSON string.", static::CODE_INVALID_OPTION_VALUE);
            }

            $query["select"] = $this->decodeSelectOption($select);
        }

        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        /** @noinspection PhpUndefinedClassInspection */
        /** @phpstan-ignore-next-line */
        $site = \Bitrix\Main\SiteTable::getList($query)->fetch();

        if (!$site) {
            return;
        }

        $this->printer->info((string) to_json($site, $flags));
    }

    /**
     * @param string $value
     * @return mixed
     * @throws \Exception
     */
    private function decodeSelectOption(string $value): mixed
    {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                "Option --select contains invalid JSON: " . json_last_error_msg(),
                static::CODE_INVALID_OPTION_VALUE
            );
        }

        return $decoded;
    }
}
