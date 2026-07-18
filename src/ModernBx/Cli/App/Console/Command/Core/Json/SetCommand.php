<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Core\Json;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\JsonPath;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\from_json;
use function ModernBx\CommonFunctions\to_json;

class SetCommand extends AppCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'json:set';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.json_set.description"))
            ->setHelp(
                $this->trans("command.json_set.help")
            )
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'pretty',
                        'p',
                        InputOption::VALUE_NONE,
                        $this->trans("option.json.pretty"),
                    ),
                    new InputArgument(
                        'path',
                        InputArgument::REQUIRED,
                        $this->trans("argument.json.path.set"),
                    ),
                    new InputArgument(
                        'value',
                        InputArgument::REQUIRED,
                        $this->trans("argument.json.value"),
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
            throw new \RuntimeException($this->trans("error.json.stdin_read"), static::CODE_IO_ERROR);
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
