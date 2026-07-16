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

class JsonGetCommand extends AppCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'json:get';

    protected function configure(): void
    {
        $this
            ->setDescription("Get value from JSON read from stdin")
            ->setHelp(
                "Reads JSON from stdin and prints the value at the specified dot-separated path as JSON. " .
                "Use an empty path to print the whole input value. Escape dots in keys with a backslash."
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
                        "Dot-separated path to the requested JSON value",
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
        $value = JsonPath::get(from_json($json), JsonPath::parse($path));
        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->printer->info((string) to_json($value, $flags));
    }
}
