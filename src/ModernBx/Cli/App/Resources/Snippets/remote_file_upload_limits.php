<?php

try {
    $parseSize = static function (string $value): int {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        $bytes = (int) $number;

        if ($unit === 'g') {
            $bytes = (int) ($number * 1024 * 1024 * 1024);
        } elseif ($unit === 'm') {
            $bytes = (int) ($number * 1024 * 1024);
        } elseif ($unit === 'k') {
            $bytes = (int) ($number * 1024);
        }

        return $bytes;
    };

    $uploadMaxFilesize = (string) ini_get('upload_max_filesize');
    $postMaxSize = (string) ini_get('post_max_size');
    $uploadMaxBytes = $parseSize($uploadMaxFilesize);
    $postMaxBytes = $parseSize($postMaxSize);
    $limits = array_filter([$uploadMaxBytes, $postMaxBytes], static fn (int $bytes): bool => $bytes > 0);
    $maxPostFileBytes = $limits === [] ? 0 : min($limits);

    // @phpstan-ignore-next-line CommandResult is mixed into remote snippets.
    echo CommandResult::successData([
        'upload_max_filesize' => $uploadMaxFilesize,
        'post_max_size' => $postMaxSize,
        'upload_max_bytes' => $uploadMaxBytes,
        'post_max_bytes' => $postMaxBytes,
        'max_post_file_bytes' => $maxPostFileBytes,
    ]);
} catch (\Throwable $err) {
    // @phpstan-ignore-next-line CommandResult is mixed into remote snippets.
    echo CommandResult::error($err->getMessage());
}
