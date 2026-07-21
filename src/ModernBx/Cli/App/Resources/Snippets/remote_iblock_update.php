<?php

/** @var int $id ID инфоблока. */
$id = 0;
/** @var array<string, mixed> $fields Поля для CIBlock::Update. */
$fields = [];

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
        throw new \RuntimeException('Не удалось подключить модуль iblock.');
    }
    if (!class_exists('CIBlock')) {
        throw new \RuntimeException('Класс CIBlock недоступен на удаленном проекте.');
    }
    if ($id <= 0) {
        throw new \RuntimeException('ID инфоблока должен быть положительным целым числом.');
    }
    if (!is_array($fields)) {
        throw new \RuntimeException('Поля инфоблока должны быть массивом.');
    }
    $iblock = new \CIBlock();
    if (!$iblock->Update($id, $fields)) {
        $lastError = is_string($iblock->LAST_ERROR ?? null) ? trim($iblock->LAST_ERROR) : '';
        throw new \RuntimeException($lastError !== '' ? $lastError : 'Не удалось обновить инфоблок.');
    }
    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
