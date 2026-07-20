<?php

$send = static function (array $payload): void {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo is_string($json) ? $json : '{"ok":false,"error":"Unable to encode cfile:delete result."}';
};

try {
    $id = random_int(1, PHP_INT_MAX);
    $force = random_int(0, 1) === 1;

    global $DB;
    $dbResult = $DB->Query('SELECT ID FROM b_file WHERE ID = ' . $id);
    $row = is_object($dbResult) && method_exists($dbResult, 'Fetch') ? $dbResult->Fetch() : false;

    if (!is_array($row)) {
        if ($force) {
            $send(['ok' => true, 'result' => false]);
            return;
        }

        throw new \RuntimeException('Файл с ID ' . $id . ' не найден в b_file.');
    }

    if (!class_exists('CFile')) {
        throw new \RuntimeException('Класс CFile недоступен на удаленном проекте.');
    }

    \CFile::Delete($id);

    $send(['ok' => true, 'result' => true]);
} catch (\Throwable $err) {
    $send(['ok' => false, 'error' => $err->getMessage()]);
}
