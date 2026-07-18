<?php

/**
 * @var string $lid Идентификатор сайта для D7 SiteTable::update.
 *                  Значение подставляется сборщиком кода как строковый литерал PHP.
 */
$lid = '__BX_CLI_SITE_LID__';

/**
 * @var array<string, mixed> $fields Поля сайта для обновления через D7 SiteTable::update.
 *                                   Значение подставляется сборщиком кода как PHP-массив.
 */
$fields = [];

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('main')) {
        throw new \RuntimeException('Не удалось подключить модуль main для работы с сайтами.');
    }

    if (!class_exists('\Bitrix\Main\SiteTable')) {
        throw new \RuntimeException('D7-класс Bitrix\\Main\\SiteTable недоступен на удаленном проекте.');
    }

    if ($lid === '') {
        throw new \RuntimeException('Идентификатор сайта LID должен быть непустой строкой.');
    }

    if (!is_array($fields)) {
        throw new \RuntimeException('Поля сайта должны быть массивом.');
    }

    $result = \Bitrix\Main\SiteTable::update($lid, $fields);

    if (!$result->isSuccess()) {
        $messages = $result->getErrorMessages();
        throw new \RuntimeException($messages === [] ? 'Не удалось обновить сайт.' : implode(PHP_EOL, $messages));
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
