<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteIBlockElementPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    public function buildGet(int $id, int $flags): string
    {
        return $this->build('remote_iblock_element_get.php', [
            '$id = 0;' => '$id = ' . $id . ';',
            '$jsonFlags = JSON_UNESCAPED_UNICODE;' => '$jsonFlags = ' . $flags . ';',
        ]);
    }

    /** @param array<string, mixed> $fields */
    public function buildUpdate(int $id, array $fields): string
    {
        return $this->build('remote_iblock_element_update.php', [
            '$id = 0;' => '$id = ' . $id . ';',
            '$fields = [];' => '$fields = ' . var_export($fields, true) . ';',
        ]);
    }

    /** @param array<string, string> $replacements */
    private function build(string $filename, array $replacements): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException(
                'Не удалось загрузить PHP-сниппет для удаленной операции с элементом инфоблока.'
            );
        }

        return $this->withSnippetMixins(strtr($snippet, $replacements));
    }
}
