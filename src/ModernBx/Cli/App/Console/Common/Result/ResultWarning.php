<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Common\Result;

final class ResultWarning
{
    /**
     * @param string $message
     * @param int $code
     */
    public function __construct(
        public readonly string $message,
        public readonly int $code,
    ) {
    }
}
