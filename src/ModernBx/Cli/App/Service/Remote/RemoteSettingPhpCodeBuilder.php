<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteSettingPhpCodeBuilder
{
    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    public function buildGet(bool $extra, string $path, int $flags): string
    {
        return $this->build('remote_setting_get.php', [
            '$extraSettings = false;' => '$extraSettings = ' . ($extra ? 'true' : 'false') . ';',
            "\$settingPath = '__BX_CLI_SETTING_PATH__';" => '$settingPath = ' . var_export($path, true) . ';',
            '$jsonFlags = JSON_UNESCAPED_UNICODE;' => '$jsonFlags = ' . $flags . ';',
        ]);
    }

    public function buildSet(bool $extra, string $path, mixed $value): string
    {
        return $this->build('remote_setting_set.php', [
            '$extraSettings = false;' => '$extraSettings = ' . ($extra ? 'true' : 'false') . ';',
            "\$settingPath = '__BX_CLI_SETTING_PATH__';" => '$settingPath = ' . var_export($path, true) . ';',
            '$settingValue = null;' => '$settingValue = ' . var_export($value, true) . ';',
        ]);
    }

    /** @param array<string, string> $replacements */
    private function build(string $filename, array $replacements): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленной операции с настройками.');
        }

        $code = strtr($snippet, $replacements);

        return preg_replace('/^<\?php\s*/', '', $code, 1) ?? $code;
    }
}
