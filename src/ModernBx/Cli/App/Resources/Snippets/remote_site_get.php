<?php

/**
 * @var array<string, mixed> $query Параметры D7 SiteTable::getList для выборки одного сайта.
 *                                  Значение подставляется сборщиком кода как PHP-массив.
 */
$query = [];

/**
 * @var int $jsonFlags Флаги json_encode для строки сайта.
 *                    Поддерживает обычный и pretty-вывод CLI.
 */
$jsonFlags = JSON_UNESCAPED_UNICODE;

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('main')) {
        throw new \RuntimeException('Не удалось подключить модуль main для работы с сайтами.');
    }

    if (!class_exists('\Bitrix\Main\SiteTable')) {
        throw new \RuntimeException('D7-класс Bitrix\\Main\\SiteTable недоступен на удаленном проекте.');
    }

    if (!is_array($query)) {
        throw new \RuntimeException('Параметры SiteTable::getList должны быть массивом.');
    }

    $site = \Bitrix\Main\SiteTable::getList($query)->fetch();
    $line = $site ? json_encode($site, $jsonFlags) : null;

    if ($site && !is_string($line)) {
        throw new \RuntimeException('Не удалось сериализовать сайт в JSON.');
    }

    echo CommandResult::success($line);
} catch (\Throwable $err) {
    echo CommandResult::error($err->getMessage());
}
