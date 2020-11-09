<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Mixin;

use ModernBx\Url\UrlImmutable;

trait BitrixAware
{
    /**
     * @return string|null
     */
    protected function getBxRootString(): ?string
    {
        $bxRoot = $this->getBxRoot();

        return $bxRoot?->toString();
    }

    /**
     * @return UrlImmutable|null
     */
    protected function getBxRoot(): ?UrlImmutable
    {
        $cwd = getcwd();

        if (!$cwd) {
            return null;
        }

        $url = UrlImmutable::create($cwd);

        while ($url->getPathSegments()) {
            $bxRoot = $url->pushPathSegment("bitrix");
            $bxRootStr = $bxRoot->toString();

            if (file_exists($bxRootStr) && is_dir($bxRootStr)) {
                return $bxRoot;
            }

            $url = $url->popPathSegment();
        }

        return null;
    }
}
