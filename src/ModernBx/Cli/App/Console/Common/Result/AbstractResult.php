<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Common\Result;

abstract class AbstractResult implements Result
{
    /**
     * @var ResultStatus
     */
    protected ResultStatus $status;

    /**
     * @var ResultNotice[]
     */
    protected array $notices;

    /**
     * @var ResultWarning[]
     */
    protected array $warnings;

    /**
     * @param ResultStatus $status
     * @param ResultNotice[] $notices
     * @param ResultWarning[] $warnings
     */
    public function __construct(ResultStatus $status, array $notices = [], array $warnings = [])
    {
        $this->status = $status;
        $this->notices = $notices;
        $this->warnings = $warnings;
    }

    /**
     * @return ResultStatus
     */
    public function getStatus(): ResultStatus
    {
        return $this->status;
    }

    /**
     * @param int|null $code
     * @return ResultNotice[]
     */
    public function getNotices(?int $code = null): array
    {
        if ($code === null) {
            return $this->notices;
        }

        return array_values(array_filter(
            $this->notices,
            static fn (ResultNotice $notice): bool => $notice->code === $code
        ));
    }

    /**
     * @param int|null $code
     * @return ResultWarning[]
     */
    public function getWarnings(?int $code = null): array
    {
        if ($code === null) {
            return $this->warnings;
        }

        return array_values(array_filter(
            $this->warnings,
            static fn (ResultWarning $warning): bool => $warning->code === $code
        ));
    }
}
