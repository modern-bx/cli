<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteOptionPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    public function buildGet(string $option, bool $unserialize): string
    {
        return $this->build('remote_option_get.php', [
            "\$option = '__BX_CLI_OPTION__';" => '$option = ' . var_export($option, true) . ';',
            '$unserialize = false;' => '$unserialize = ' . ($unserialize ? 'true' : 'false') . ';',
        ]);
    }

    public function buildSet(string $option, string $value): string
    {
        return $this->build('remote_option_set.php', [
            "\$option = '__BX_CLI_OPTION__';" => '$option = ' . var_export($option, true) . ';',
            "\$value = '__BX_CLI_VALUE__';" => '$value = ' . var_export($value, true) . ';',
        ]);
    }

    public function buildDelete(string $option): string
    {
        return $this->build('remote_option_delete.php', [
            "\$option = '__BX_CLI_OPTION__';" => '$option = ' . var_export($option, true) . ';',
        ]);
    }

    /** @param array<string, string> $replacements */
    private function build(string $filename, array $replacements): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленной операции с опциями.');
        }

        return $this->withSnippetMixins(strtr($snippet, $replacements));
    }
}
