<?php

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
$directories = ['cache', 'managed_cache', 'stack_cache'];

try {
    $errors = [];
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')
        : '';

    if ($documentRoot === '') {
        throw new RuntimeException('DOCUMENT_ROOT не определен.');
    }

    $bxRoot = $documentRoot . '/bitrix';

    $removePath = static function (string $path) use (&$removePath, &$errors): void {
        if (is_link($path) || is_file($path)) {
            if (!file_exists($path) && !is_link($path)) {
                return;
            }

            if (!@unlink($path)) {
                $error = error_get_last();
                $errors[] = [
                    'path' => $path,
                    'reason' => is_array($error) ? (string) $error['message'] : null,
                ];
            }

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $items = @scandir($path);

        if ($items === false) {
            $error = error_get_last();
            $errors[] = [
                'path' => $path,
                'reason' => is_array($error) ? (string) $error['message'] : null,
            ];
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $removePath($path . DIRECTORY_SEPARATOR . $item);
        }

        if (!@rmdir($path)) {
            $error = error_get_last();
            $errors[] = [
                'path' => $path,
                'reason' => is_array($error) ? (string) $error['message'] : null,
            ];
        }
    };

    foreach ($directories as $directory) {
        if (!is_string($directory) || !in_array($directory, ['cache', 'managed_cache', 'stack_cache'], true)) {
            throw new RuntimeException('Некорректная директория кеша.');
        }

        $path = $bxRoot . '/' . $directory;

        if (!is_dir($path)) {
            continue;
        }

        $items = @scandir($path);

        if ($items === false) {
            $error = error_get_last();
            $errors[] = [
                'path' => $path,
                'reason' => is_array($error) ? (string) $error['message'] : null,
            ];
            continue;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $removePath($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    if ($errors !== []) {
        echo CommandResult::error('Не удалось удалить часть файлов кеша.', $errors);
        return;
    }

    echo CommandResult::success(true);
} catch (Throwable $err) {
    echo CommandResult::error($err->getMessage());
}
