<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Mixin;

trait PHPCode
{
    /**
     * @param array<mixed> $ary
     * @return string|null
     */
    protected function arrayExport(array $ary): ?string
    {
        $result = var_export($ary, true);

        if (!$result) {
            return null;
        }

        /** @var string $result */
        $result = preg_replace("/^( *)(.*)/m", '$1$1$2', $result);
        /** @var array<string> $array */
        $array = preg_split("/\r\n|\n|\r/", $result);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);

        return join(PHP_EOL, array_filter(["["] + $array));
    }
}
