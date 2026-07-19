<?php

/** @var string[] $options Список имен опций в формате module.option.siteId. */
$options = [];

/** @var bool $unserialize Нужно ли распаковать сериализованное значение. */
$unserialize = false;

try {
    if (!is_array($options)) {
        throw new RuntimeException('Список опций должен быть массивом.');
    }

    if (!class_exists('\Bitrix\Main\Config\Option')) {
        throw new RuntimeException('D7-класс Bitrix\\Main\\Config\\Option недоступен на удаленном проекте.');
    }

    $lines = [];

    foreach ($options as $option) {
        if (!is_string($option) || $option === '') {
            throw new RuntimeException('Имя опции должно быть непустой строкой.');
        }

        [$moduleName, $optionName, $siteId] = explode('.', $option);

        $optionValue = \Bitrix\Main\Config\Option::get(
            $moduleName,
            $optionName,
            '',
            $siteId ?: false
        );

        if ($unserialize) {
            $unserializedValue = @unserialize($optionValue);
        }

        $line = json_encode($unserializedValue ?? $optionValue, JSON_UNESCAPED_UNICODE);

        if (!is_string($line)) {
            throw new RuntimeException('Не удалось сериализовать опцию в JSON.');
        }

        $lines[] = $line;
        unset($unserializedValue);
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success($lines);
} catch (Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
