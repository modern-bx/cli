<?php

/** @var array<string, mixed> $fields Поля для CIBlockElement::Add. */
$fields = [];

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
        throw new \RuntimeException('Не удалось подключить модуль iblock.');
    }

    if (!class_exists('CIBlockElement')) {
        throw new \RuntimeException('Класс CIBlockElement недоступен на удаленном проекте.');
    }

    if (!is_array($fields)) {
        throw new \RuntimeException('Поля элемента инфоблока должны быть массивом.');
    }

    $element = new \CIBlockElement();
    $id = $element->Add($fields);

    if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id) || (int) $id <= 0) {
        $lastError = is_string($element->LAST_ERROR ?? null) ? trim($element->LAST_ERROR) : '';
        throw new \RuntimeException($lastError !== '' ? $lastError : 'Не удалось добавить элемент инфоблока.');
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success($id);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
