<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Boilerplate;

use ModernBx\Cli\App\Console\Mixin\PHPCode;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use function ModernBx\CommonFunctions\deep_get;
use function ModernBx\CommonFunctions\format;
use function ModernBx\CommonFunctions\to_json;

class ConfigIncreaseCommand extends ConfigSetCommand
{
    use PHPCode;

    /**
     * @var string
     */
    protected static $defaultName = 'config:inc';

    protected function configure(): void
    {
        $this
            ->setDescription("Increase the value of specified option in config.php by 1")
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        "path",
                        InputArgument::REQUIRED,
                        "Path to the config key",
                    ),
                    new InputOption(
                        'target',
                        't',
                        InputOption::VALUE_OPTIONAL,
                        "Config file name",
                        $this->getDefaultConfigFile(),
                    ),
                    new InputOption(
                        'force',
                        'f',
                        InputOption::VALUE_OPTIONAL,
                        "Create the file if not found or is malformed",
                        false,
                    ),
                    new InputOption(
                        'cast',
                        'c',
                        InputOption::VALUE_OPTIONAL,
                        "PHP type of the value to cast to",
                        "int",
                    ),
                ]),
            );
    }

    /**
     * @param array<mixed> $config
     * @param string $path
     * @return mixed
     * @throws \Exception
     */
    protected function getCurrentValue(array $config, string $path): mixed
    {
        $result = deep_get($config, $path);

        if (isset($result)) {
            if ($this->isVerbose()) {
                $this->printer->formatInfo("Current value: {value].", [
                    "value" => (string) to_json($result),
                ]);
            }
        } elseif (!$this->isForceSet()) {
            throw new \Exception(format("Target option not found in the file: {path}.", [
                "path" => $path,
            ]), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return $result;
    }

    /**
     * @param InputInterface $input
     * @return int
     */
    protected function getValue(InputInterface $input): int
    {
        /** @var int|string|bool $currentValue */
        $currentValue = $this->currentValue;

        return intval($currentValue) + 1;
    }
}
