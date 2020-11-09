<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Mixin;

use FilesystemIterator;

trait IO
{
    /**
     * @param string $path
     */
    protected function deleteDirectory(string $path): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $todo = ($fileInfo->isDir() ? "rmdir" : "unlink");
            $todo($fileInfo->getRealPath());
        }

        rmdir($path);
    }
}
