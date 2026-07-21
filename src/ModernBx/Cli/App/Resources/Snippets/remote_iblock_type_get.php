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
    $result = \CIBlockType::GetList([], ['=ID' => $id]);
    $fields = $result->Fetch();
    if (!$fields) {
        throw new \RuntimeException('Тип инфоблока с ID ' . $id . ' не найден.');
    }
    /** @phpstan-ignore-next-line */
    echo CommandResult::success($fields);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
