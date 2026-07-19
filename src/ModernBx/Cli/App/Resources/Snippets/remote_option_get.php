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

    $defaultValue = "\0BX_CLI_OPTION_NOT_FOUND\0";

    $optionValue = \Bitrix\Main\Config\Option::get(
        $moduleName,
        $optionName,
        $defaultValue,
        $siteId !== null ? $siteId : false
    );

    if ($optionValue === $defaultValue) {
        throw new RuntimeException(sprintf('Опция %s не найдена в бд.', $option));
    }

    if ($unserialize) {
        $unserializedValue = @unserialize($optionValue);
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success($unserializedValue ?? $optionValue);
} catch (Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
