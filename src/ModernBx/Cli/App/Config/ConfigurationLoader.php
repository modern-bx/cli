<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Config;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;

class ConfigurationLoader extends FileLoader
{
    /**
     * @param string $resource
     * @param null $type
     * @return mixed
     */
    public function load($resource, $type = null): mixed
    {
        return Yaml::parse((string) file_get_contents($resource));
    }

    /**
     * @param mixed $resource
     * @param null $type
     * @return bool
     */
    public function supports($resource, $type = null): bool
    {
        return is_string($resource) && ('yml' === pathinfo($resource, PATHINFO_EXTENSION));
    }
}
