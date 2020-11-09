<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Service;

use ModernBx\Cli\App\Config\Configuration;
use ModernBx\Cli\App\Config\ConfigurationLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;

use function ModernBx\CommonFunctions\deep_get;

final class ConfigurationService
{
    /**
     * @var array<mixed>
     */
    protected array $config;

    public function __construct()
    {
        $directories = [$_SERVER["DOCUMENT_ROOT"]];
        $locator = new FileLocator($directories);

        $loader = new ConfigurationLoader($locator);
        /** @var string $configPath */
        $configPath = $locator->locate('config.yml');

        /** @var array<mixed> $configValues */
        $configValues = $loader->load($configPath);

        $processor = new Processor();
        $configuration = new Configuration();

        $this->config = $processor->processConfiguration(
            $configuration,
            $configValues,
        );
    }

    /**
     * @return array<mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param string $path
     * @param null $defaultValue
     * @return mixed
     */
    public function getConfigKey(string $path, $defaultValue = null): mixed
    {
        return deep_get($this->config, $path, $defaultValue);
    }
}
