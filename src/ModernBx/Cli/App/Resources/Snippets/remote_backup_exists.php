<?php

$filename = '__BX_CLI_BACKUP_FILENAME__';
$send = static function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ok":false}';
};

try {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('DOCUMENT_ROOT не определен.');
    }

    if (basename($filename) !== $filename) {
        throw new \RuntimeException('Некорректное имя файла резервной копии.');
    }

    $backupDirectory = $documentRoot . '/bitrix/backup';

    if (!is_dir($backupDirectory)) {
        throw new \RuntimeException('Директория резервных копий не найдена: /bitrix/backup');
    }

    $send(['ok' => true, 'result' => is_file($backupDirectory . '/' . $filename)]);
} catch (\Throwable $err) {
    $send(['ok' => false, 'error' => $err->getMessage()]);
}
