<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Mixin\Common;

use FilesystemIterator;

trait IO
{
    /**
     * @param string $path
     */
    protected function deleteDirectory(string $path): void
    {
        $this->deleteDirectoryContent($path);
        rmdir($path);
    }

    protected function deleteDirectoryContent(string $path): void
    {
        $errors = $this->collectDeleteDirectoryContentErrors($path);

        if ($errors !== []) {
            $first = $errors[0];

            throw new \RuntimeException(sprintf(
                'Ошибка удаления (%s): %s',
                $first['reason'] ?: 'причина неизвестна',
                $first['path'],
            ));
        }
    }

    /**
     * @return array<int, array{path: string, reason: string|null}>
     */
    protected function collectDeleteDirectoryContentErrors(string $path): array
    {
        $errors = [];

        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
        } catch (\Throwable $err) {
            return [['path' => $path, 'reason' => $err->getMessage()]];
        }

        foreach ($files as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $realPath = $fileInfo->getRealPath();

            if ($realPath === false) {
                $errors[] = ['path' => $fileInfo->getPathname(), 'reason' => 'не удалось определить реальный путь'];
                continue;
            }

            $deleted = $fileInfo->isDir() ? @rmdir($realPath) : @unlink($realPath);

            if (!$deleted) {
                $error = error_get_last();
                $errors[] = [
                    'path' => $realPath,
                    'reason' => is_array($error) ? (string) $error['message'] : null,
                ];
            }
        }

        return $errors;
    }
}
