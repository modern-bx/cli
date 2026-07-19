<?php

/** @var string $option Имя опции в формате module.option.siteId. */
$option = '__BX_CLI_OPTION__';

/** @var string $value Новое значение опции. */
$value = '__BX_CLI_VALUE__';

try {
    $parts = explode('.', $option);

    if (count($parts) < 2 || count($parts) > 3 || in_array('', $parts, true)) {
        throw new RuntimeException('Имя опции должно быть в формате module.option[.lid].');
    }

    [$moduleName, $optionName] = $parts;
    $siteId = $parts[2] ?? null;

    if (!class_exists('\Bitrix\Main\Config\Option')) {
        throw new RuntimeException('D7-класс Bitrix\\Main\\Config\\Option недоступен на удаленном проекте.');
    }

    \Bitrix\Main\Config\Option::set(
        $moduleName,
        $optionName,
        $value,
        $siteId ?? ''
    );

    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
