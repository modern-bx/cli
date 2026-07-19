<?php

/** @var string $option Имя опции в формате module.option.siteId. */
$option = '__BX_CLI_OPTION__';

/** @var string $value Новое значение опции. */
$value = '__BX_CLI_VALUE__';

try {
    if (!class_exists('\Bitrix\Main\Config\Option')) {
        throw new RuntimeException('D7-класс Bitrix\\Main\\Config\\Option недоступен на удаленном проекте.');
    }

    [$moduleName, $optionName, $siteId] = explode('.', $option);

    \Bitrix\Main\Config\Option::set(
        $moduleName,
        $optionName,
        $value,
        $siteId ?: ''
    );

    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
