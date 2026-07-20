<?php

/**
 * @var array<string, mixed> $fields Поля сайта для добавления через D7 SiteTable::add.
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

    if (!is_array($fields)) {
        throw new \RuntimeException('Поля сайта должны быть массивом.');
    }

    $result = \Bitrix\Main\SiteTable::add($fields);

    if (!$result->isSuccess()) {
        $messages = $result->getErrorMessages();
        throw new \RuntimeException($messages === [] ? 'Не удалось добавить сайт.' : implode(PHP_EOL, $messages));
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success($result->getId());
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
