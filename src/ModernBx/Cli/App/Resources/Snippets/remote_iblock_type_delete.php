<?php

/** @var string $id ID типа инфоблока. */
$id = '';

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
    if (!\CIBlockType::Delete($id)) {
        throw new \RuntimeException('Не удалось удалить тип инфоблока.');
    }
    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
