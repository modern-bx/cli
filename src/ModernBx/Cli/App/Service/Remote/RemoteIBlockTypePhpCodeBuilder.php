<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteIBlockTypePhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    public function buildGet(string $id): string
    {
        return $this->build('remote_iblock_type_get.php', [
            '$id = \'\';' => '$id = ' . var_export($id, true) . ';',
        ]);
    }

    /** @param array<string, mixed> $fields */
    public function buildAdd(array $fields): string
    {
        return $this->build('remote_iblock_type_add.php', [
            '$fields = [];' => '$fields = ' . var_export($fields, true) . ';',
        ]);
    }

    public function buildDelete(string $id): string
    {
        return $this->build('remote_iblock_type_delete.php', [
            '$id = \'\';' => '$id = ' . var_export($id, true) . ';',
        ]);
    }

    /** @param array<string, mixed> $fields */
    public function buildUpdate(string $id, array $fields): string
    {
        return $this->build('remote_iblock_type_update.php', [
            '$id = \'\';' => '$id = ' . var_export($id, true) . ';',
            '$fields = [];' => '$fields = ' . var_export($fields, true) . ';',
        ]);
    }

    /** @param array<string, string> $replacements */
    private function build(string $filename, array $replacements): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException(
                'Не удалось загрузить PHP-сниппет для удаленной операции с типом инфоблока.'
            );
        }

        return $this->withSnippetMixins(strtr($snippet, $replacements));
    }
}
