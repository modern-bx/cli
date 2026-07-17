<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Mixin\Bx;

final class ModuleLifecycleResult
{
    const STATUS_INSTALLED = 'installed';
    const STATUS_ALREADY_INSTALLED = 'already_installed';
    const STATUS_UNINSTALLED = 'uninstalled';
    const STATUS_NOT_INSTALLED = 'not_installed';

    /**
     * @var string
     */
    protected string $moduleCode;

    /**
     * @var string
     */
    protected string $status;

    /**
     * @param string $moduleCode
     * @param string $status
     */
    public function __construct(string $moduleCode, string $status)
    {
        $this->moduleCode = $moduleCode;
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getModuleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}
