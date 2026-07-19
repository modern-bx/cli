<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteOptionPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    /** @param string[] $options */
    public function buildGet(array $options, bool $unserialize): string
    {
        return $this->build('remote_option_get.php', [
            '$options = [];' => '$options = ' . var_export(array_values($options), true) . ';',
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
