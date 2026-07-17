<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Common\Result;

interface Result
{
    /**
     * @return ResultStatus
     */
    public function getStatus(): ResultStatus;

    /**
     * @param int|null $code
     * @return ResultNotice[]
     */
    public function getNotices(?int $code = null): array;

    /**
     * @param int|null $code
     * @return ResultWarning[]
     */
    public function getWarnings(?int $code = null): array;
}
