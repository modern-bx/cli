<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteIBlockSectionPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    public function buildGet(int $id): string
    {
        return $this->build('remote_iblock_section_get.php', [
            '$id = 0;' => '$id = ' . $id . ';',
        ]);
    }

    /** @param array<string, mixed> $fields */
    public function buildAdd(array $fields): string
    {
        return $this->build('remote_iblock_section_add.php', [
            '$fields = [];' => '$fields = ' . var_export($fields, true) . ';',
        ]);
    }

    public function buildDelete(int $id): string
    {
        return $this->build('remote_iblock_section_delete.php', [
            '$id = 0;' => '$id = ' . $id . ';',
        ]);
    }

    /** @param array<string, mixed> $fields */
    public function buildUpdate(int $id, array $fields): string
    {
        return $this->build('remote_iblock_section_update.php', [
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
                'Не удалось загрузить PHP-сниппет для удаленной операции с разделом инфоблока.'
            );
        }

        return $this->withSnippetMixins(strtr($snippet, $replacements));
    }
}
