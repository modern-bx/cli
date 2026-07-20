<?php

/**
 * @var string $lid Идентификатор сайта для D7 SiteTable::delete.
 *                  Значение подставляется сборщиком кода как строковый литерал PHP.
 */
$lid = '__BX_CLI_SITE_LID__';

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

    $result = \Bitrix\Main\SiteTable::delete($lid);

    if (!$result->isSuccess()) {
        $messages = $result->getErrorMessages();
        throw new \RuntimeException($messages === [] ? 'Не удалось удалить сайт.' : implode(PHP_EOL, $messages));
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
