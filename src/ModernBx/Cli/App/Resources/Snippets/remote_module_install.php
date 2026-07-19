<?php

/** @var mixed $moduleCode Код модуля подставляется сборщиком кода. */
$moduleCode = '__BX_CLI_MODULE_CODE__';

try {
    if (!is_string($moduleCode) || $moduleCode === '') {
        throw new RuntimeException('Код модуля должен быть непустой строкой.');
    }

    if (!class_exists('\Bitrix\Main\ModuleManager')) {
        throw new RuntimeException('D7-класс Bitrix\\Main\\ModuleManager недоступен на удаленном проекте.');
    }

    if (\Bitrix\Main\ModuleManager::isModuleInstalled($moduleCode)) {
        echo CommandResult::successData(['warning' => 'MODULE_ALREADY_INSTALLED']);
        return;
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')
        : '';

    if ($documentRoot === '') {
        throw new RuntimeException('DOCUMENT_ROOT не определен.');
    }

    $modulePath = null;
    $modulePaths = [
        $documentRoot . '/bitrix/modules/' . $moduleCode,
        $documentRoot . '/local/modules/' . $moduleCode,
    ];

    foreach ($modulePaths as $path) {
        if (is_dir($path)) {
            $modulePath = $path;
            break;
        }
    }

    if ($modulePath === null) {
        throw new RuntimeException('Модуль ' . $moduleCode . ' не найден.');
    }

    $installFile = $modulePath . '/install/index.php';
    if (!is_file($installFile)) {
        throw new RuntimeException('Файл установки модуля ' . $moduleCode . ' не найден.');
    }

    require_once $installFile;

    $installerClass = str_replace('.', '_', $moduleCode);
    if (!class_exists($installerClass)) {
        throw new RuntimeException('Класс установки ' . $installerClass . ' не найден.');
    }

    $installer = new $installerClass();
    if (!method_exists($installer, 'DoInstall')) {
        throw new RuntimeException('Метод DoInstall класса ' . get_class($installer) . ' не найден.');
    }

    $installer->DoInstall();

    echo CommandResult::success(['module' => $moduleCode]);
} catch (Throwable $err) {
    echo CommandResult::error($err->getMessage());
}
