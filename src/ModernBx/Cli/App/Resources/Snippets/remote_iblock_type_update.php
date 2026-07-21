<?php

/** @var string $id ID типа инфоблока. */
$id = '';
/** @var array<string, mixed> $fields Поля для CIBlockType::Update. */
$fields = [];

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
        throw new \RuntimeException('Не удалось подключить модуль iblock.');
    }
    if (!class_exists('CIBlockType')) {
        throw new \RuntimeException('Класс CIBlockType недоступен на удаленном проекте.');
    }
    if (trim($id) === '') {
        throw new \RuntimeException('ID типа инфоблока должен быть непустой строкой.');
    }
    if (!is_array($fields)) {
        throw new \RuntimeException('Поля типа инфоблока должны быть массивом.');
    }
    $iblockType = new \CIBlockType();
    if (!$iblockType->Update($id, $fields)) {
        $lastError = is_string($iblockType->LAST_ERROR ?? null) ? trim($iblockType->LAST_ERROR) : '';
        throw new \RuntimeException($lastError !== '' ? $lastError : 'Не удалось обновить тип инфоблока.');
    }
    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
