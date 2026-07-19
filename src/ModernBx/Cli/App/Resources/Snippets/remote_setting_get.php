<?php

/**
 * @var bool $extraSettings Читать .settings_extra.php вместо .settings.php.
 */
$extraSettings = false;

/**
 * @var string $settingPath Путь настройки через точку.
 *                         Значение подставляется сборщиком кода как строковый литерал PHP.
 */
$settingPath = '__BX_CLI_SETTING_PATH__';

/**
 * @var int $jsonFlags Флаги json_encode для значения настройки.
 *                    Поддерживает обычный и pretty-вывод CLI.
 */
$jsonFlags = JSON_UNESCAPED_UNICODE;

try {
    $file = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\')
        . '/bitrix/'
        . ($extraSettings ? '.settings_extra.php' : '.settings.php');

    if (!is_file($file)) {
        throw new \RuntimeException('Settings file has not been found: ' . $file);
    }

    $settings = require $file;

    if (!is_array($settings)) {
        throw new \RuntimeException('Settings file must return an array: ' . $file);
    }

    $segments = array_values(array_filter(
        explode('.', $settingPath),
        static fn (string $segment): bool => $segment !== ''
    ));

    if ($segments === []) {
        throw new \RuntimeException('Setting path must not be empty.');
    }

    $root = array_shift($segments);

    if (!array_key_exists($root, $settings)) {
        throw new \RuntimeException('Setting path has not been found.');
    }

    if (!is_array($settings[$root]) || !array_key_exists('value', $settings[$root])) {
        throw new \RuntimeException('Setting root must contain a value key.');
    }

    $cursor = $settings[$root]['value'];

    foreach ($segments as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            throw new \RuntimeException('Setting path has not been found.');
        }

        $cursor = $cursor[$segment];
    }

    $line = json_encode($cursor, $jsonFlags);

    if (!is_string($line)) {
        throw new \RuntimeException('Не удалось сериализовать настройку в JSON.');
    }

    echo CommandResult::success($line);
} catch (\Throwable $err) {
    echo CommandResult::error($err->getMessage());
}
