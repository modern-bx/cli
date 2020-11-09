<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service;

final class ClassAliasLoader
{
    /**
     * @var RuntimeInfo
     */
    protected RuntimeInfo $runtimeInfo;

    /**
     * @var ConfigurationService
     */
    protected ConfigurationService $config;

    /**
     * @param RuntimeInfo $runtimeInfo
     * @param ConfigurationService $config
     */
    public function __construct(RuntimeInfo $runtimeInfo, ConfigurationService $config)
    {
        $this->runtimeInfo = $runtimeInfo;
        $this->config = $config;
    }

    /**
     * PHP-Scoper в сочетании с Box не умеет или не хочет полноценно обрабатывать whitelist классов
     * в глобальном пространстве, поэтому мы построим этот whitelist сами.
     */
    public function loadClassAliases(): void
    {
        /** @var array<string> $classes */
        $classes = $this->config->getConfigKey("whitelist.classes");

        foreach ($classes as $class) {
            /** @var class-string $class */

            class_alias($class, $this->runtimeInfo->getScopedClass($class));
        }
    }
}
