<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

$path = $_POST['__remote_file_delete_path']
    ?? json_decode('__REMOTE_FILE_DELETE_PATH__', true);

try {
    if (!is_string($path) || $path === '') {
        throw new RuntimeException('Некорректный путь удаляемого файла.');
    }

    $documentRoot = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/');
    $file = $documentRoot . '/' . ltrim($path, '/');

    if (is_file($file) && !unlink($file)) {
        throw new RuntimeException('Не удалось удалить временный архив: ' . $path);
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
