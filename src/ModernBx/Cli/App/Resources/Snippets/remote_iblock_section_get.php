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

    $minimalResult = \CIBlockSection::GetList([], ['ID' => $id], false, ['ID', 'IBLOCK_ID'], ['nTopCount' => 1]);
    $minimalSection = $minimalResult->GetNext();

    if (!$minimalSection) {
        throw new \RuntimeException('Раздел инфоблока с ID ' . $id . ' не найден.');
    }

    $iblockId = $minimalSection['IBLOCK_ID'] ?? null;
    if ((!is_int($iblockId) && !is_string($iblockId)) || !ctype_digit((string) $iblockId) || (int) $iblockId <= 0) {
        throw new \RuntimeException('Не удалось определить IBLOCK_ID раздела инфоблока.');
    }

    $result = \CIBlockSection::GetList(
        [],
        ['ID' => $id, 'IBLOCK_ID' => (int) $iblockId],
        false,
        ['*', 'UF_*'],
        ['nTopCount' => 1]
    );
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
