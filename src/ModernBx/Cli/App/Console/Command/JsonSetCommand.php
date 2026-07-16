<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command;

use ModernBx\Cli\App\Service\JsonPath;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\from_json;
use function ModernBx\CommonFunctions\to_json;

class JsonSetCommand extends AppCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'json:set';

    protected function configure(): void
    {
        $this
            ->setDescription("Set value in JSON read from stdin")
            ->setHelp(
                "Reads JSON from stdin, decodes the value argument as JSON, " .
                "sets it at the specified dot-separated path, and prints the resulting JSON. " .
                "Use an empty path to replace the whole input value. Escape dots in keys with a backslash."
            )
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'pretty',
                        'p',
                        InputOption::VALUE_NONE,
                        "Pretty-print output JSON",
                    ),
                    new InputArgument(
                        'path',
                        InputArgument::REQUIRED,
                        "Dot-separated path to the JSON value that should be changed",
                    ),
                    new InputArgument(
                        'value',
                        InputArgument::REQUIRED,
                        "New value encoded as JSON",
                    ),
                ]),
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \JsonException
     * @throws \RuntimeException
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        $json = file_get_contents("php://stdin");

        if ($json === false) {
            throw new \RuntimeException("Unable to read JSON from stdin.", static::CODE_IO_ERROR);
        }

        /** @var string $path */
        $path = $input->getArgument("path");
        /** @var string $rawValue */
        $rawValue = $input->getArgument("value");
        $value = JsonPath::set(from_json($json), JsonPath::parse($path), from_json($rawValue));
        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->printer->info((string) to_json($value, $flags));
    }
}
