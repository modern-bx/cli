<?php

/** @var string $option Имя опции в формате module.option.siteId. */
$option = '__BX_CLI_OPTION__';

/** @var bool $unserialize Нужно ли распаковать сериализованное значение. */
$unserialize = false;

try {
    if (!is_string($option) || $option === '') {
        throw new RuntimeException('Имя опции должно быть непустой строкой.');
    }

    $parts = explode('.', $option);

    if (count($parts) < 2 || count($parts) > 3 || in_array('', $parts, true)) {
        throw new RuntimeException('Имя опции должно быть в формате module.option[.lid].');
    }

    [$moduleName, $optionName] = $parts;
    $siteId = $parts[2] ?? null;

    if (!class_exists('\Bitrix\Main\Config\Option')) {
        throw new RuntimeException('D7-класс Bitrix\\Main\\Config\\Option недоступен на удаленном проекте.');
    }

    $optionValue = \Bitrix\Main\Config\Option::get(
        $moduleName,
        $optionName,
        '',
        $siteId !== null ? $siteId : false
    );

    if ($unserialize) {
        $unserializedValue = @unserialize($optionValue);
    }

    $line = json_encode($unserializedValue ?? $optionValue, JSON_UNESCAPED_UNICODE);

    if (!is_string($line)) {
        throw new RuntimeException('Не удалось сериализовать опцию в JSON.');
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success($line);
} catch (Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
