<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Boilerplate;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Console\Mixin\PHPCode;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\deep_get;
use function ModernBx\CommonFunctions\deep_set;
use function ModernBx\CommonFunctions\format;
use function ModernBx\CommonFunctions\to_json;

class ConfigSetCommand extends BxCommand
{
    use PHPCode;

    /**
     * @var string
     */
    protected static $defaultName = 'config:set';

    /**
     * @var string|null
     */
    protected ?string $configFilePath;

    /**
     * @var bool|null
     */
    protected ?bool $forceSet;

    /**
     * @var array<mixed>
     */
    protected array $config;

    /**
     * @var mixed
     */
    protected mixed $currentValue;

    /**
     * @var mixed
     */
    protected mixed $value;

    /**
     * @return bool|null
     */
    public function isForceSet(): ?bool
    {
        return $this->forceSet;
    }

    protected function configure(): void
    {
        $this
            ->setDescription("Set the value of specified option in config.php")
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        "path",
                        InputArgument::REQUIRED,
                        "Path to config key",
                    ),
                    new InputArgument(
                        "value",
                        InputArgument::REQUIRED,
                        "Config key value",
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

        $this->forceSet = $input->getOption("force") !== false;

        /** @var string $target */
        $target = $input->getOption("target");
        /** @var string $path */
        $path = $input->getArgument("path");
        /** @var string|null $cast */
        $cast = $input->getOption("cast");

        $this->configFilePath = $this->getConfigFilePath($target);
        $this->config = $this->loadConfigFile($this->configFilePath);
        $this->currentValue = $this->getCurrentValue($this->config, $path);
        $this->value = $this->getValue($input);

        if ($cast) {
            settype($this->value, $cast);
        }

        deep_set($this->config, $path, $this->value);

        $this->saveConfigFile($this->configFilePath, $this->config);
    }

    /**
     * @return string
     */
    protected function getDefaultConfigFile(): string
    {
        return "config.php";
    }

    /**
     * @param string $target
     * @return array<mixed>
     * @throws \Exception
     */
    protected function loadConfigFile(string $target): array
    {
        if (is_dir($target)) {
            throw new \Exception(format("Target config file is a directory: {path}.", [
                "path" => $target
            ]), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $config = [];

        if (file_exists($target)) {
            if (!is_readable($target) || !is_writable($target)) {
                throw new \Exception(format("Target config file is not accessible: {path}.", [
                    "path" => $target,
                ]), static::CODE_INVALID_ARGUMENT_VALUE);
            }

            $config = require $target;

            if (!is_array($config)) {
                if (!$this->isForceSet()) {
                    throw new \Exception(format("Invalid file content: {path}.", [
                        "path" => $target,
                    ]), static::CODE_INVALID_FILE_CONTENT);
                }

                $config = [];
            }
        } elseif (!$this->isForceSet()) {
            throw new \Exception(format("Target config file does not exist: {path}.", [
                "path" => $target,
            ]), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return $config;
    }

    /**
     * @param string $target
     * @return string
     */
    protected function getConfigFilePath(string $target): string
    {
        return $this->getDocumentRoot()
            ->pushPathSegment("local")
            ->pushPathSegment("php_interface")
            ->pushPathSegment($target)
            ->withoutTrailingSlash()
            ->toString();
    }

    /**
     * @param string $configFilePath
     * @param array<mixed> $config
     * @throws \Exception
     */
    protected function saveConfigFile(string $configFilePath, array $config): void
    {
        $result = file_put_contents($configFilePath, join("\n", [
            "<?php\n",
            "return " . $this->arrayExport($config) . ";\n",
        ]));

        if (!$result) {
            throw new \Exception(format("Could not overwrite the target file: {path}.", [
                "path" => $configFilePath,
            ]), static::CODE_IO_ERROR);
        }
    }

    /**
     * @param array<mixed> $config
     * @param string $path
     * @return mixed
     * @throws \JsonException
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
        }

        return $result;
    }

    /**
     * @param InputInterface $input
     * @return mixed
     */
    protected function getValue(InputInterface $input): mixed
    {
        return $input->getArgument("value");
    }
}
