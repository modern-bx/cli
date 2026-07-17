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
     * @param ResultNotice[] $notices
     * @param ResultWarning[] $warnings
     */
    public function __construct(
        string $moduleCode,
        ResultStatus $status,
        array $notices = [],
        array $warnings = []
    ) {
        parent::__construct($status, $notices, $warnings);

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
