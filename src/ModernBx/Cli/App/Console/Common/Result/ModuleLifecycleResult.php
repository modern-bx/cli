<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Common\Result;

final class ModuleLifecycleResult extends AbstractResult
{
    /**
     * @var string
     */
    protected string $moduleCode;

    /**
     * @param string $moduleCode
     * @param ResultStatus $status
     * @param ResultWarning[] $warnings
     * @param ResultNotice[] $notices
     */
    public function __construct(
        string $moduleCode,
        ResultStatus $status,
        array $warnings = [],
        array $notices = []
    ) {
        parent::__construct($status, $warnings, $notices);

        $this->moduleCode = $moduleCode;
    }

    /**
     * @return string
     */
    public function getModuleCode(): string
    {
        return $this->moduleCode;
    }
}
