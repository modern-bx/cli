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

    $result = \CIBlock::GetList([], ['ID' => $id], true);
    $fields = $result->Fetch();

    if (!$fields) {
        throw new \RuntimeException('Инфоблок с ID ' . $id . ' не найден.');
    }

    foreach (array_keys($fields) as $field) {
        if (!is_string($field) || !str_starts_with($field, '~')) {
            continue;
        }
        $normalizedField = substr($field, 1);
        if ($normalizedField === '') {
            unset($fields[$field]);
            continue;
        }
        $fields[$normalizedField] = $fields[$field];
        unset($fields[$field]);
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success($fields);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
