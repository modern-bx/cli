<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service;

final class EnvFile
{
    /**
     * @param string $content
     * @param string $key
     * @return string|null
     */
    public static function get(string $content, string $key): ?string
    {
        foreach (self::splitLines($content) as $line) {
            $match = self::matchAssignment($line, $key);

            if ($match) {
                return self::decodeValue($match["value"]);
            }
        }

        return null;
    }

    /**
     * @param string $content
     * @param string $key
     * @param string $value
     * @return string
     */
    public static function set(string $content, string $key, string $value): string
    {
        $lines = self::splitLines($content);
        $encodedValue = self::encodeValue($value);
        $changed = false;

        foreach ($lines as $index => $line) {
            $match = self::matchAssignment($line, $key);

            if (!$match) {
                continue;
            }

            $lines[$index] = $match["prefix"] . $key . "=" . $encodedValue;
            $changed = true;
        }

        if (!$changed) {
            if ($content !== "" && !str_ends_with($content, "\n")) {
                $lines[] = "";
            }

            $lines[] = $key . "=" . $encodedValue;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param string $content
     * @return array<string>
     */
    protected static function splitLines(string $content): array
    {
        if ($content === "") {
            return [];
        }

        return explode("\n", rtrim(str_replace("\r\n", "\n", $content), "\n"));
    }

    /**
     * @param string $line
     * @param string $key
     * @return array{prefix: string, value: string}|null
     */
    protected static function matchAssignment(string $line, string $key): ?array
    {
        $pattern = "/^(?<prefix>\\s*(?:export\\s+)?)(?<key>" . preg_quote($key, "/") . ")\\s*=\\s*(?<value>.*)$/";

        if (!preg_match($pattern, $line, $matches)) {
            return null;
        }

        return [
            "prefix" => $matches["prefix"],
            "value" => $matches["value"],
        ];
    }

    /**
     * @param string $value
     * @return string
     */
    protected static function decodeValue(string $value): string
    {
        $value = trim($value);

        if ($value === "") {
            return "";
        }

        $quote = $value[0];

        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
            $value = substr($value, 1, -1);

            if ($quote === '"') {
                return stripcslashes($value);
            }

            return str_replace("\\'", "'", $value);
        }

        return rtrim((string) preg_replace('/\s+#.*$/', '', $value));
    }

    /**
     * @param string $value
     * @return string
     */
    protected static function encodeValue(string $value): string
    {
        if ($value !== "" && preg_match('/^[A-Za-z0-9_.,:\/@-]+$/', $value)) {
            return $value;
        }

        return '"' . addcslashes($value, "\\\"\n\r\t") . '"';
    }
}
