<?php

/** @var int $id ID элемента инфоблока. */
$id = 0;

/** @var int $jsonFlags Флаги json_encode для вывода полей элемента. */
$jsonFlags = JSON_UNESCAPED_UNICODE;

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
        throw new \RuntimeException('Не удалось подключить модуль iblock.');
    }

    if (!class_exists('CIBlockElement')) {
        throw new \RuntimeException('Класс CIBlockElement недоступен на удаленном проекте.');
    }

    if ($id <= 0) {
        throw new \RuntimeException('ID элемента инфоблока должен быть положительным целым числом.');
    }

    $result = \CIBlockElement::GetList([], ['ID' => $id], false, ['nTopCount' => 1], ['*']);
    $element = $result->GetNextElement();

    if (!$element) {
        throw new \RuntimeException('Элемент инфоблока с ID ' . $id . ' не найден.');
    }

    $fields = $element->GetFields();
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

    $line = json_encode($fields, $jsonFlags);
    if (!is_string($line)) {
        throw new \RuntimeException('Не удалось сериализовать поля элемента инфоблока в JSON.');
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success($line);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
