<?php

$backupName = '__BX_CLI_BACKUP_NAME__';
$send = static function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ok":false}';
};

try {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('DOCUMENT_ROOT не определен.');
    }

    $backupDirectory = $documentRoot . '/bitrix/backup';
    $mainPath = $backupDirectory . '/' . $backupName;

    if (!is_file($mainPath)) {
        throw new \RuntimeException('Основной файл резервной копии не найден: ' . $backupName);
    }

    $paths = ['/bitrix/backup/' . $backupName];
    $volumePaths = glob($mainPath . '.*') ?: [];
    $volumes = [];

    foreach ($volumePaths as $path) {
        $suffix = substr($path, strlen($mainPath) + 1);

        if (!ctype_digit($suffix) || (int) $suffix <= 0 || !is_file($path)) {
            continue;
        }

        $volumes[(int) $suffix] = '/bitrix/backup/' . basename($path);
    }

    ksort($volumes, SORT_NUMERIC);
    $send(['ok' => true, 'result' => array_merge($paths, array_values($volumes))]);
} catch (\Throwable $err) {
    $send(['ok' => false, 'error' => $err->getMessage()]);
}
