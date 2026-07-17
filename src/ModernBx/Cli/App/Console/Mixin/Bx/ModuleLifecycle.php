<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Mixin\Bx;

use ModernBx\Cli\App\Console\Common\Result\ModuleLifecycleResult;
use ModernBx\Cli\App\Console\Common\Result\ResultStatus;
use ModernBx\Cli\App\Console\Common\Result\ResultWarning;

trait ModuleLifecycle
{

    /**
     * @param string $moduleCode
     * @return ModuleLifecycleResult
     * @throws \Exception
     */
    protected function installModule(string $moduleCode): ModuleLifecycleResult
    {
        /** @phpstan-ignore-next-line */
        if (\Bitrix\Main\ModuleManager::isModuleInstalled($moduleCode)) {
            return new ModuleLifecycleResult(
                $moduleCode,
                ResultStatus::STATUS_SUCCESS,
                [],
                [new ResultWarning(
                    $this->trans("warning.module.already_installed", ["%module%" => $moduleCode]),
                    ModuleLifecycleWarningCode::MODULE_ALREADY_INSTALLED
                )]
            );
        }

        $installer = $this->getModuleInstaller($moduleCode);
        $this->callInstallerMethod($installer, 'DoInstall');

        return new ModuleLifecycleResult($moduleCode, ResultStatus::STATUS_SUCCESS);
    }

    /**
     * @param string $moduleCode
     * @return ModuleLifecycleResult
     * @throws \Exception
     */
    protected function uninstallModule(string $moduleCode): ModuleLifecycleResult
    {
        /** @phpstan-ignore-next-line */
        if (!\Bitrix\Main\ModuleManager::isModuleInstalled($moduleCode)) {
            return new ModuleLifecycleResult(
                $moduleCode,
                ResultStatus::STATUS_SUCCESS,
                [],
                [new ResultWarning(
                    $this->trans("warning.module.not_installed", ["%module%" => $moduleCode]),
                    ModuleLifecycleWarningCode::MODULE_NOT_INSTALLED
                )]
            );
        }

        $installer = $this->getModuleInstaller($moduleCode);
        $this->callInstallerMethod($installer, 'DoUninstall');

        return new ModuleLifecycleResult($moduleCode, ResultStatus::STATUS_SUCCESS);
    }

    /**
     * @param mixed $moduleCode
     * @return string
     * @throws \Exception
     */
    protected function getModuleCode(mixed $moduleCode): string
    {
        if (!is_string($moduleCode) || $moduleCode === '') {
            throw new \Exception(
                $this->trans("error.module.code_string"),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        return $moduleCode;
    }

    /**
     * @param string $moduleCode
     * @return object
     * @throws \Exception
     */
    protected function getModuleInstaller(string $moduleCode): object
    {
        $modulePath = $this->getModulePath($moduleCode);

        if ($modulePath === null) {
            throw new \Exception(
                $this->trans("error.module.not_found", ["%module%" => $moduleCode]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $installFile = $modulePath . "/install/index.php";

        if (!is_file($installFile)) {
            throw new \Exception(
                $this->trans("error.module.install_file_not_found", ["%module%" => $moduleCode]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        require_once $installFile;

        $installerClass = str_replace('.', '_', $moduleCode);

        if (!class_exists($installerClass)) {
            throw new \Exception(
                $this->trans("error.module.install_class_not_found", ["%class%" => $installerClass]),
                static::CODE_INVALID_FILE_CONTENT
            );
        }

        return new $installerClass();
    }

    /**
     * @param object $installer
     * @param string $method
     * @return void
     * @throws \Exception
     */
    protected function callInstallerMethod(object $installer, string $method): void
    {
        if (!method_exists($installer, $method)) {
            throw new \Exception(
                $this->trans("error.module.install_method_not_found", [
                    "%method%" => $method,
                    "%class%" => get_class($installer),
                ]),
                static::CODE_INVALID_FILE_CONTENT
            );
        }

        $installer->$method();
    }

    /**
     * @param string $moduleCode
     * @return string|null
     */
    protected function getModulePath(string $moduleCode): ?string
    {
        $paths = [
            $this->bxRoot->pushPathSegment("modules")->pushPathSegment($moduleCode)->toString(),
            $this->documentRoot
                ->pushPathSegment("local")
                ->pushPathSegment("modules")
                ->pushPathSegment($moduleCode)
                ->toString(),
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }
}
