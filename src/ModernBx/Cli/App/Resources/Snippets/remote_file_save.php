<?php

/**
 * @var string $pathJson JSON-encoded path relative to document root.
 *                       Value is substituted by RemoteFilePhpCodeBuilder.
 */
$pathJson = '__REMOTE_FILE_SAVE_PATH__';

$bufferLevel = ob_get_level();
ob_start();

$send = static function (array $payload) use ($bufferLevel): void {
    while (ob_get_level() > $bufferLevel) {
        ob_end_clean();
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $flags);

    if (!is_string($json)) {
        $json = json_encode([
            'ok' => false,
            'error' => 'Не удалось сериализовать результат file:save: ' . json_last_error_msg(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    echo is_string($json) ? $json : '{"ok":false,"error":"Unable to encode file:save result."}';
};

try {
    $path = json_decode($pathJson, true);

    if (!is_string($path) || $path === '') {
        throw new \RuntimeException('Некорректный путь к файлу.');
    }

    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('DOCUMENT_ROOT не определен.');
    }

    $absolutePath = $documentRoot . $path;

    if (!is_file($absolutePath)) {
        throw new \RuntimeException('Файл не найден: ' . $path);
    }

    if (!is_readable($absolutePath)) {
        throw new \RuntimeException('Файл недоступен для чтения: ' . $path);
    }

    if (!class_exists('CFile')) {
        throw new \RuntimeException('Класс CFile недоступен на удаленном проекте.');
    }

    $file = \CFile::MakeFileArray($absolutePath);

    if (!is_array($file)) {
        throw new \RuntimeException('Не удалось подготовить файл для сохранения: ' . $path);
    }

    $id = (int) \CFile::SaveFile($file, '');

    if ($id <= 0) {
        throw new \RuntimeException('Не удалось сохранить файл в b_file: ' . $path);
    }

    global $DB;
    $dbResult = $DB->Query('SELECT * FROM b_file WHERE ID = ' . $id);
    $row = is_object($dbResult) && method_exists($dbResult, 'Fetch') ? $dbResult->Fetch() : false;

    if (!is_array($row)) {
        throw new \RuntimeException('Не удалось получить строку b_file для ID ' . $id . '.');
    }

    $send(['ok' => true, 'result' => $row]);
} catch (\Throwable $err) {
    $send(['ok' => false, 'error' => $err->getMessage()]);
}
