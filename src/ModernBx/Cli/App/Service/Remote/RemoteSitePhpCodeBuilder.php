<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteSitePhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    /** @param array<string, mixed> $query */
    public function buildList(array $query, int $flags): string
    {
        return $this->build('remote_site_list.php', [
            '$query = [];' => '$query = ' . var_export($query, true) . ';',
            '$jsonFlags = JSON_UNESCAPED_UNICODE;' => '$jsonFlags = ' . $flags . ';',
        ]);
    }

    /** @param array<string, mixed> $query */
    public function buildGet(array $query, int $flags): string
    {
        return $this->build('remote_site_get.php', [
            '$query = [];' => '$query = ' . var_export($query, true) . ';',
            '$jsonFlags = JSON_UNESCAPED_UNICODE;' => '$jsonFlags = ' . $flags . ';',
        ]);
    }

    /** @param array<string, mixed> $fields */
    public function buildUpdate(string $lid, array $fields): string
    {
        return $this->build('remote_site_update.php', [
            "\$lid = '__BX_CLI_SITE_LID__';" => '$lid = ' . var_export($lid, true) . ';',
            '$fields = [];' => '$fields = ' . var_export($fields, true) . ';',
        ]);
    }

    /** @param array<string, string> $replacements */
    private function build(string $filename, array $replacements): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленной операции с сайтом.');
        }

        $code = strtr($snippet, $replacements);

        return $this->withSnippetMixins($code);
    }
}
