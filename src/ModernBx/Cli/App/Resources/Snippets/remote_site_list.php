<?php

/**
 * @var array<string, mixed> $query Параметры D7 SiteTable::getList: filter/order/select.
 *                                  Значение подставляется сборщиком кода как PHP-массив.
 */
$query = [];

/**
 * @var int $jsonFlags Флаги json_encode для каждой строки сайта.
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

    $cursor = \Bitrix\Main\SiteTable::getList($query);
    $sites = [];

    while ($site = $cursor->fetch()) {
        $sites[] = $site;
    }

    echo json_encode([
        'ok' => true,
        'result' => $sites,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $err) {
    echo json_encode([
        'ok' => false,
        'error' => $err->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
