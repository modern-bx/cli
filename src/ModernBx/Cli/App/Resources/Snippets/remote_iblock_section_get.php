<?php

/** @var int $id ID раздела инфоблока. */
$id = 0;

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

    $result = \CIBlockSection::GetList([], ['ID' => $id], false, ['*', 'UF_*'], ['nTopCount' => 1]);
    $section = $result->GetNext();

    if (!$section) {
        throw new \RuntimeException('Раздел инфоблока с ID ' . $id . ' не найден.');
    }

    $fields = $section;
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
