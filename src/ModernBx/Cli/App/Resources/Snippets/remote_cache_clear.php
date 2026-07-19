<?php

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
$directories = ['cache', 'managed_cache', 'stack_cache'];

try {
    $errors = [];
    $stats = [];
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')
        : '';

    if ($documentRoot === '') {
        throw new RuntimeException('DOCUMENT_ROOT не определен.');
    }

    $bxRoot = $documentRoot . '/bitrix';
    $lastError = static function (): ?string {
        $error = error_get_last();

        return is_array($error) ? (string) $error['message'] : null;
    };
    $addError = static function (string $directory, string $path, ?string $reason) use (&$errors, &$stats): void {
        $stats[$directory]['errors']++;
        $errors[] = [
            'directory' => $directory,
            'path' => $path,
            'reason' => $reason,
        ];
    };

    $removePath = static function (
        string $path,
        string $directory
    ) use (
        &$removePath,
        &$stats,
        $addError,
        $lastError
    ): void {
        if (is_link($path) || is_file($path)) {
            if (!file_exists($path) && !is_link($path)) {
                return;
            }

            $size = @filesize($path);

            if (@unlink($path)) {
                $stats[$directory]['deleted_files']++;
                $stats[$directory]['freed_bytes'] += is_int($size) ? $size : 0;
                return;
            }

            $addError($directory, $path, $lastError());
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $items = @scandir($path);

        if ($items === false) {
            $addError($directory, $path, $lastError());
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $removePath($path . DIRECTORY_SEPARATOR . $item, $directory);
        }

        if (!@rmdir($path)) {
            $addError($directory, $path, $lastError());
        }
    };

    foreach ($directories as $directory) {
        if (!is_string($directory) || !in_array($directory, ['cache', 'managed_cache', 'stack_cache'], true)) {
            throw new RuntimeException('Некорректная директория кеша.');
        }

        $stats[$directory] = [
            'directory' => $directory,
            'deleted_files' => 0,
            'freed_bytes' => 0,
            'errors' => 0,
        ];
        $path = $bxRoot . '/' . $directory;

        if (!is_dir($path)) {
            continue;
        }

        $items = @scandir($path);

        if ($items === false) {
            $addError($directory, $path, $lastError());
            continue;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $removePath($path . DIRECTORY_SEPARATOR . $item, $directory);
        }
    }

    if ($errors !== []) {
        echo CommandResult::error('Не удалось удалить часть файлов кеша.', $errors, $stats);
        return;
    }

    echo CommandResult::success($stats);
} catch (Throwable $err) {
    echo CommandResult::error($err->getMessage());
}
