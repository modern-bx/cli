<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Vendor;

final class PackageStrategyRegistry
{
    /** @var array<string, PackageStrategy> */
    private array $strategies;

    public function __construct()
    {
        $adminer = new AdminerPackageStrategy();
        $this->strategies = [$adminer->getName() => $adminer];
    }

    public function get(string $package): PackageStrategy
    {
        $package = strtolower(trim($package));

        if (!isset($this->strategies[$package])) {
            throw new \RuntimeException('Неизвестный пакет. Доступно: adminer.');
        }

        return $this->strategies[$package];
    }
}
