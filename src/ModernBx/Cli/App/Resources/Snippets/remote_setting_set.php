<?php

/**
 * @var bool $extraSettings Записывать .settings_extra.php вместо .settings.php.
 */
$extraSettings = false;

/**
 * @var string $settingPath Путь настройки через точку.
 *                         Значение подставляется сборщиком кода как строковый литерал PHP.
 */
$settingPath = '__BX_CLI_SETTING_PATH__';

/**
 * @var mixed $settingValue Значение настройки.
 *                         Значение подставляется сборщиком кода как PHP-литерал.
 */
$settingValue = null;

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

    if (!array_key_exists($root, $settings) || !is_array($settings[$root])) {
        $settings[$root] = [];
    }

    if (!array_key_exists('value', $settings[$root]) || !is_array($settings[$root]['value'])) {
        $settings[$root]['value'] = [];
    }

    $cursor = &$settings[$root]['value'];

    foreach ($segments as $segment) {
        if (!is_array($cursor)) {
            throw new \RuntimeException('Unable to set nested value for scalar setting path.');
        }

        if (!array_key_exists($segment, $cursor) || !is_array($cursor[$segment])) {
            $cursor[$segment] = [];
        }

        $cursor = &$cursor[$segment];
    }

    $cursor = $settingValue;
    $content = "<?php\n\nreturn " . var_export($settings, true) . ";\n";

    if (file_put_contents($file, $content) === false) {
        throw new \RuntimeException('Unable to write settings file: ' . $file);
    }

    echo json_encode([
        'ok' => true,
        'result' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $err) {
    echo json_encode([
        'ok' => false,
        'error' => $err->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
