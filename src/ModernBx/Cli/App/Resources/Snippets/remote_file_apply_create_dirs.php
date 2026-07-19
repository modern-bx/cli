<?php

$payload = [];

/** @var array{dest?: string, directories?: array<int, string>} $payload */

try {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('Не удалось определить document root удаленного проекта.');
    }

    $join = static function (string $dest, string $relative): string {
        return '/' . trim(trim($dest, '/') . '/' . trim($relative, '/'), '/');
    };
    $created = 0;
    $dest = (string) ($payload['dest'] ?? '');

    foreach (($payload['directories'] ?? []) as $directory) {
        $target = $join($dest, $directory);
        $fullTarget = $documentRoot . $target;

        if (is_dir($fullTarget)) {
            continue;
        }

        if (!mkdir($fullTarget, 0775, true) && !is_dir($fullTarget)) {
            throw new \RuntimeException('Не удалось создать директорию: ' . $target);
        }

        $created++;
    }

    // @phpstan-ignore-next-line CommandResult is mixed into remote snippets.
    echo CommandResult::successData(['created' => $created]);
} catch (\Throwable $err) {
    // @phpstan-ignore-next-line CommandResult is mixed into remote snippets.
    echo CommandResult::error($err->getMessage());
}
