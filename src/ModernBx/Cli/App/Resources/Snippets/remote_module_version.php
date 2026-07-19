<?php

/** @var mixed $moduleCodes Список модулей подставляется сборщиком кода. */
$moduleCodes = [];

try {
    if (!is_array($moduleCodes)) {
        throw new RuntimeException('Список модулей должен быть массивом.');
    }

    if (!class_exists('\Bitrix\Main\ModuleManager')) {
        throw new RuntimeException('D7-класс Bitrix\\Main\\ModuleManager недоступен на удаленном проекте.');
    }

    $versions = [];
    foreach ($moduleCodes as $moduleCode) {
        if (!is_string($moduleCode) || $moduleCode === '') {
            throw new RuntimeException('Код модуля должен быть непустой строкой.');
        }

        $versions[] = \Bitrix\Main\ModuleManager::getVersion($moduleCode);
    }

    echo CommandResult::success($versions);
} catch (Throwable $err) {
    echo CommandResult::error($err->getMessage());
}
