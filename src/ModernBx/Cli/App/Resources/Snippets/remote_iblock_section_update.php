<?php

/** @var int $id ID раздела инфоблока. */
$id = 0;

/** @var array<string, mixed> $fields Поля для CIBlockSection::Update. */
$fields = [];

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
        throw new \RuntimeException('Не удалось подключить модуль iblock.');
    }

    if (!class_exists('CIBlockSection')) {
        throw new \RuntimeException('Класс CIBlockSection недоступен на удаленном проекте.');
    }

    if ($id <= 0) {
        throw new \RuntimeException('ID раздела инфоблока должен быть положительным целым числом.');
    }

    if (!is_array($fields)) {
        throw new \RuntimeException('Поля раздела инфоблока должны быть массивом.');
    }

    $element = new \CIBlockSection();
    if (!$element->Update($id, $fields)) {
        $lastError = is_string($element->LAST_ERROR ?? null) ? trim($element->LAST_ERROR) : '';
        throw new \RuntimeException($lastError !== '' ? $lastError : 'Не удалось обновить раздел инфоблока.');
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
