<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Mixin;

trait SettingFile
{
    /**
     * @param bool $extra
     * @return string
     */
    protected function getSettingsFile(bool $extra): string
    {
        return $this->bxRoot->toString() . ($extra ? ".settings_extra.php" : ".settings.php");
    }

    /**
     * @param string $file
     * @return array<string, mixed>
     * @throws \Exception
     */
    protected function loadSettings(string $file): array
    {
        if (!file_exists($file)) {
            throw new \Exception("Settings file has not been found: " . $file, static::CODE_IO_ERROR);
        }

        $settings = require $file;

        if (!is_array($settings)) {
            throw new \Exception("Settings file must return an array: " . $file, static::CODE_INVALID_FILE_CONTENT);
        }

        /** @var array<string, mixed> $settings */
        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     * @param string $file
     * @return void
     * @throws \Exception
     */
    protected function saveSettings(array $settings, string $file): void
    {
        $content = "<?php\n\nreturn " . var_export($settings, true) . ";\n";

        if (file_put_contents($file, $content) === false) {
            throw new \Exception("Unable to write settings file: " . $file, static::CODE_IO_ERROR);
        }
    }

    /**
     * @param string $path
     * @return array<int, string>
     * @throws \Exception
     */
    protected function getPathSegments(string $path): array
    {
        $segments = array_values(array_filter(
            explode(".", $path),
            static fn (string $segment): bool => $segment !== ""
        ));

        if (!$segments) {
            throw new \Exception("Setting path must not be empty.", static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return $segments;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, string> $segments
     * @return mixed
     * @throws \Exception
     */
    protected function getSettingValue(array $settings, array $segments): mixed
    {
        $cursor = $this->getRootValue($settings, array_shift($segments));

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                throw new \Exception("Setting path has not been found.", static::CODE_INVALID_ARGUMENT_VALUE);
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, string> $segments
     * @param mixed $value
     * @return void
     * @throws \Exception
     */
    protected function setSettingValue(array &$settings, array $segments, mixed $value): void
    {
        $root = array_shift($segments);

        if ($root === null) {
            throw new \Exception("Setting path must not be empty.", static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if (!array_key_exists($root, $settings) || !is_array($settings[$root])) {
            $settings[$root] = [];
        }

        if (!array_key_exists("value", $settings[$root]) || !is_array($settings[$root]["value"])) {
            $settings[$root]["value"] = [];
        }

        $cursor = &$settings[$root]["value"];

        foreach ($segments as $segment) {
            if (!is_array($cursor)) {
                throw new \Exception(
                    "Unable to set nested value for scalar setting path.",
                    static::CODE_INVALID_ARGUMENT_VALUE
                );
            }

            if (!array_key_exists($segment, $cursor) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor = $value;
    }

    /**
     * @param array<string, mixed> $settings
     * @param string|null $root
     * @return mixed
     * @throws \Exception
     */
    private function getRootValue(array $settings, ?string $root): mixed
    {
        if ($root === null || !array_key_exists($root, $settings)) {
            throw new \Exception("Setting path has not been found.", static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if (!is_array($settings[$root]) || !array_key_exists("value", $settings[$root])) {
            throw new \Exception("Setting root must contain a value key.", static::CODE_INVALID_FILE_CONTENT);
        }

        return $settings[$root]["value"];
    }
}
