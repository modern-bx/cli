<?php

/** @var int $id ID элемента инфоблока. */
$id = 0;

try {
    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
        throw new \RuntimeException('Не удалось подключить модуль iblock.');
    }

    if (!class_exists('CIBlockElement')) {
        throw new \RuntimeException('Класс CIBlockElement недоступен на удаленном проекте.');
    }

    if ($id <= 0) {
        throw new \RuntimeException('ID элемента инфоблока должен быть положительным целым числом.');
    }

    if (!\CIBlockElement::Delete($id)) {
        global $APPLICATION;

        $message = '';
        if (is_object($APPLICATION) && method_exists($APPLICATION, 'GetException')) {
            $exception = $APPLICATION->GetException();
            if (is_object($exception) && method_exists($exception, 'GetString')) {
                $exceptionMessage = $exception->GetString();
                $message = is_string($exceptionMessage) ? trim($exceptionMessage) : '';
            }
        }

        throw new \RuntimeException($message !== '' ? $message : 'Не удалось удалить элемент инфоблока.');
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
