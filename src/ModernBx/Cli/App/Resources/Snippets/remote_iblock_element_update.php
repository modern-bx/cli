<?php

/** @var int $id ID элемента инфоблока. */
$id = 0;

/** @var array<string, mixed> $fields Поля для CIBlockElement::Update. */
$fields = [];

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
        throw new \RuntimeException('Не удалось подключить модуль iblock.');
    }

    if (!class_exists('CIBlockElement')) {
        throw new \RuntimeException('Класс CIBlockElement недоступен на удаленном проекте.');
    }

    if ($id <= 0) {
        throw new \RuntimeException('ID элемента инфоблока должен быть положительным целым числом.');
    }

    if (!is_array($fields)) {
        throw new \RuntimeException('Поля элемента инфоблока должны быть массивом.');
    }

    $element = new \CIBlockElement();
    if (!$element->Update($id, $fields)) {
        $lastError = is_string($element->LAST_ERROR ?? null) ? trim($element->LAST_ERROR) : '';
        throw new \RuntimeException($lastError !== '' ? $lastError : 'Не удалось обновить элемент инфоблока.');
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
