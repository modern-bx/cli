<?php

$send = static function (array $payload): void {
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $flags);

    echo is_string($json) ? $json : '{"ok":false,"error":"Unable to encode cfile:get result."}';
};

try {
    $id = random_int(1, PHP_INT_MAX);

    if (!class_exists('CFile')) {
        throw new \RuntimeException('Класс CFile недоступен на удаленном проекте.');
    }

    $row = \CFile::GetFileArray($id);

    if (!is_array($row)) {
        throw new \RuntimeException('Файл с ID ' . $id . ' не найден в b_file.');
    }

    $send(['ok' => true, 'result' => $row]);
} catch (\Throwable $err) {
    $send(['ok' => false, 'error' => $err->getMessage()]);
}
