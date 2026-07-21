<?php

/** @var array<string, mixed> $fields Поля для CIBlock::Add. */
$fields = [];

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
        throw new \RuntimeException('Не удалось подключить модуль iblock.');
    }
    if (!class_exists('CIBlock')) {
        throw new \RuntimeException('Класс CIBlock недоступен на удаленном проекте.');
    }
    if (!is_array($fields)) {
        throw new \RuntimeException('Поля инфоблока должны быть массивом.');
    }
    $iblock = new \CIBlock();
    $id = $iblock->Add($fields);
    if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id) || (int) $id <= 0) {
        $lastError = is_string($iblock->LAST_ERROR ?? null) ? trim($iblock->LAST_ERROR) : '';
        throw new \RuntimeException($lastError !== '' ? $lastError : 'Не удалось добавить инфоблок.');
    }
    /** @phpstan-ignore-next-line */
    echo CommandResult::success($id);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
