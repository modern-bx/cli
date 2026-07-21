<?php

/** @var array<string, mixed> $fields Поля для CIBlockType::Add. */
$fields = [];

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
        throw new \RuntimeException('Не удалось подключить модуль iblock.');
    }
    if (!class_exists('CIBlockType')) {
        throw new \RuntimeException('Класс CIBlockType недоступен на удаленном проекте.');
    }
    if (!is_array($fields)) {
        throw new \RuntimeException('Поля типа инфоблока должны быть массивом.');
    }
    $id = $fields['ID'] ?? null;
    if (!is_string($id) || trim($id) === '') {
        throw new \RuntimeException('ID типа инфоблока должен быть непустой строкой.');
    }
    $iblockType = new \CIBlockType();
    if (!$iblockType->Add($fields)) {
        $lastError = is_string($iblockType->LAST_ERROR ?? null) ? trim($iblockType->LAST_ERROR) : '';
        throw new \RuntimeException($lastError !== '' ? $lastError : 'Не удалось добавить тип инфоблока.');
    }
    /** @phpstan-ignore-next-line */
    echo CommandResult::success(trim($id));
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
