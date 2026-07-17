<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Mixin\Bx;

trait ModuleLifecycleOutput
{
    /**
     * @param ModuleLifecycleResult $result
     * @return void
     */
    protected function printModuleLifecycleResult(ModuleLifecycleResult $result): void
    {
        $moduleCode = $result->getModuleCode();

        switch ($result->getStatus()) {
            case ModuleLifecycleResult::STATUS_INSTALLED:
                $this->printer->info($this->trans("message.module.installed", ["%module%" => $moduleCode]));
                break;

            case ModuleLifecycleResult::STATUS_ALREADY_INSTALLED:
                $this->printer->put(
                    $this->trans("warning.module.already_installed", ["%module%" => $moduleCode]),
                    "comment"
                );
                break;

            case ModuleLifecycleResult::STATUS_UNINSTALLED:
                $this->printer->info($this->trans("message.module.uninstalled", ["%module%" => $moduleCode]));
                break;

            case ModuleLifecycleResult::STATUS_NOT_INSTALLED:
                $this->printer->put(
                    $this->trans("warning.module.not_installed", ["%module%" => $moduleCode]),
                    "comment"
                );
                break;
        }
    }
}
