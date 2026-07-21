<?php

/** @var int $id ID инфоблока. */
$id = 0;

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
    if (!\CIBlock::Delete($id)) {
        throw new \RuntimeException('Не удалось удалить инфоблок.');
    }
    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
