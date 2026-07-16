<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Service;

final class JsonPath
{
    /**
     * @param string $path
     * @return array<string>
     */
    public static function parse(string $path): array
    {
        if ($path === "") {
            return [];
        }

        $segments = [];
        $segment = "";
        $escaped = false;

        foreach (str_split($path) as $char) {
            if ($escaped) {
                $segment .= $char;
                $escaped = false;
                continue;
            }

            if ($char === "\\") {
                $escaped = true;
                continue;
            }

            if ($char === ".") {
                $segments[] = $segment;
                $segment = "";
                continue;
            }

            $segment .= $char;
        }

        if ($escaped) {
            $segment .= "\\";
        }

        $segments[] = $segment;

        return $segments;
    }

    /**
     * @param mixed $value
     * @param array<string> $path
     * @return mixed
     */
    public static function get(mixed $value, array $path): mixed
    {
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param mixed $source
     * @param array<string> $path
     * @param mixed $value
     * @return mixed
     */
    public static function set(mixed $source, array $path, mixed $value): mixed
    {
        if ($path === []) {
            return $value;
        }

        if (!is_array($source)) {
            $source = [];
        }

        $pointer = &$source;

        foreach ($path as $index => $segment) {
            if ($index === count($path) - 1) {
                $pointer[$segment] = $value;
                break;
            }

            if (!array_key_exists($segment, $pointer) || !is_array($pointer[$segment])) {
                $pointer[$segment] = [];
            }

            $pointer = &$pointer[$segment];
        }

        return $source;
    }
}
