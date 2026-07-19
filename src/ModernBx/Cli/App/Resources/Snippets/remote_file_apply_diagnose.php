<?php

$payload = [];

/** @var array{dest?: string, directories?: array<int, string>, files?: array<int, array{relative?: string, size?: int}>, force?: bool} $payload */

try {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('Не удалось определить document root удаленного проекта.');
    }

    $join = static function (string $dest, string $relative): string {
        return '/' . trim(trim($dest, '/') . '/' . trim($relative, '/'), '/');
    };
    $formatBytes = static function (int $bytes): string {
        $units = ['байт', 'КБ', 'МБ', 'ГБ'];
        $value = (float) $bytes;

        foreach ($units as $index => $unit) {
            if ($value < 1024 || $index === count($units) - 1) {
                return $index === 0
                    ? $bytes . ' ' . $unit
                    : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . ' ' . $unit;
            }

            $value /= 1024;
        }

        return $bytes . ' байт';
    };
    $notices = [];
    $errors = [];
    $dest = (string) ($payload['dest'] ?? '');
    $force = ($payload['force'] ?? false) === true;

    foreach (($payload['directories'] ?? []) as $directory) {
        $target = $join($dest, $directory);
        $fullTarget = $documentRoot . $target;

        if (is_dir($fullTarget)) {
            $notices[] = 'Директория уже существует: ' . $target;
        } elseif (file_exists($fullTarget)) {
            $errors[] = 'На месте директории существует файл: ' . $target;
        }
    }

    foreach (($payload['files'] ?? []) as $file) {
        $relative = $file['relative'] ?? null;

        if (!is_string($relative)) {
            continue;
        }

        $size = (int) ($file['size'] ?? 0);
        $target = $join($dest, $relative);
        $fullTarget = $documentRoot . $target;
        $message = 'Файл уже существует: ' . $target . ' (' . $formatBytes($size) . ' (' . $size . ' байт))';

        if (is_file($fullTarget)) {
            $force ? $notices[] = $message : $errors[] = $message;
        } elseif (is_dir($fullTarget)) {
            $errors[] = 'На месте файла существует директория: ' . $target;
        }
    }

    // @phpstan-ignore-next-line CommandResult is mixed into remote snippets.
    echo CommandResult::successData([
        'notices' => $notices,
        'errors' => $errors,
    ]);
} catch (\Throwable $err) {
    // @phpstan-ignore-next-line CommandResult is mixed into remote snippets.
    echo CommandResult::error($err->getMessage());
}
