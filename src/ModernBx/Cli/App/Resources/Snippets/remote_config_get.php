<?php

/** @var string[] $parameters */
$parameters = [];

try {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    $settingsFiles = [
        $documentRoot . '/bitrix/.settings_extra.php',
        $documentRoot . '/bitrix/.settings.php',
    ];
    $settingsConnections = [];

    foreach ($settingsFiles as $settingsFile) {
        if (!is_file($settingsFile)) {
            $settingsConnections[] = [];
            continue;
        }

        $settings = require $settingsFile;
        $connection = is_array($settings)
            ? ($settings['connections']['value']['default'] ?? [])
            : [];
        $settingsConnections[] = is_array($connection) ? $connection : [];
    }

    $dbconn = [];
    $dbconnFile = $documentRoot . '/bitrix/php_interface/dbconn.php';
    if (is_file($dbconnFile)) {
        $DBHost = $DBLogin = $DBPassword = $DBName = null;
        require $dbconnFile;
        /** @var mixed $DBHost */
        /** @var mixed $DBLogin */
        /** @var mixed $DBPassword */
        /** @var mixed $DBName */
        $dbconn = [
            'host' => $DBHost,
            'username' => $DBLogin,
            'password' => $DBPassword,
            'database' => $DBName,
        ];
    }

    $constantNames = [
        'host' => ['DBHost', 'DB_HOST'],
        'username' => ['DBLogin', 'DB_LOGIN', 'DBUsername', 'DB_USERNAME'],
        'password' => ['DBPassword', 'DB_PASSWORD'],
        'database' => ['DBName', 'DB_NAME'],
    ];
    $settingNames = [
        'host' => ['host'],
        'username' => ['username', 'login'],
        'password' => ['password'],
        'database' => ['database'],
    ];
    $values = [];

    foreach ($parameters as $parameter) {
        if ($parameter === 'db.engine') {
            // @phpstan-ignore-next-line Bitrix API доступен на удаленном проекте.
            $values[] = \Bitrix\Main\Application::getConnection()->getConnectionType();
            continue;
        }

        $name = substr($parameter, 3);
        $found = false;
        $value = null;

        foreach ($settingsConnections as $connection) {
            foreach ($settingNames[$name] as $settingName) {
                if (array_key_exists($settingName, $connection)) {
                    $value = $connection[$settingName];
                    $found = true;
                    break 2;
                }
            }
        }

        if (!$found && array_key_exists($name, $dbconn) && $dbconn[$name] !== null) {
            $value = $dbconn[$name];
            $found = true;
        }

        if (!$found) {
            foreach ($constantNames[$name] as $constantName) {
                if (defined($constantName)) {
                    $value = constant($constantName);
                    $found = true;
                    break;
                }
            }
        }

        if (!$found || !is_scalar($value)) {
            throw new \RuntimeException('Параметр не найден: ' . $parameter);
        }

        $values[] = (string) $value;
    }

    // @phpstan-ignore-next-line CommandResult добавляется сборщиком remote-сниппетов.
    echo CommandResult::success($values);
} catch (\Throwable $err) {
    // @phpstan-ignore-next-line CommandResult добавляется сборщиком remote-сниппетов.
    echo CommandResult::error($err->getMessage());
}
