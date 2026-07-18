<?php

declare(strict_types=1);

namespace Bitrix\Main;

final class SiteTable
{
    /** @var array<string, mixed>|null */
    public static ?array $lastQuery = null;

    /** @var array<string, mixed>|false|null */
    public static array|false|null $nextRow = null;

    /** @param array<string, mixed> $query */
    public static function getList(array $query): SiteTableResult
    {
        self::$lastQuery = $query;

        return new SiteTableResult(self::$nextRow);
    }
}
