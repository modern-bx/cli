<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Vendor;

final class AdminerPackageStrategy implements PackageStrategy
{
    public function getName(): string
    {
        return 'adminer';
    }

    public function supportsDefaultPath(): bool
    {
        return true;
    }

    public function getMainFilename(): string
    {
        return 'adminer.php';
    }

    public function isInstalledLocally(string $documentRoot, string $path): bool
    {
        return is_file(rtrim($documentRoot, '/') . $path . '/' . $this->getMainFilename());
    }

    public function buildRemoteInstalledCheck(string $path): string
    {
        $relativeFile = var_export($path . '/' . $this->getMainFilename(), true);

        return sprintf(
            'echo json_encode(["installed" => is_file(rtrim((string) $_SERVER["DOCUMENT_ROOT"], "/") . %s)]);',
            $relativeFile,
        );
    }
}
