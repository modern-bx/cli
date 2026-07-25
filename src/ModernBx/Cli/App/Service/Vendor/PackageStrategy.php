<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Vendor;

interface PackageStrategy
{
    public function getName(): string;

    public function supportsDefaultPath(): bool;

    public function getMainFilename(): string;

    public function isInstalledLocally(string $documentRoot, string $path): bool;

    public function buildRemoteInstalledCheck(string $path): string;
}
