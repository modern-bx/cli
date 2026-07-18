<?php

declare(strict_types=1);

namespace Bitrix\Main;

final class SiteTableResult
{
    /** @param array<string, mixed>|false|null $row */
    public function __construct(private array|false|null $row)
    {
    }

    /** @return array<string, mixed>|false|null */
    public function fetch(): array|false|null
    {
        return $this->row;
    }
}
