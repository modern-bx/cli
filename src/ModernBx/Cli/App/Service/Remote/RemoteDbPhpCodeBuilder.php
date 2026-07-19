<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteDbPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    /** @param array<int, string>|null $tables */
    public function buildDump(?array $tables): string
    {
        return $this->build('remote_db_dump.php', [
            '$tableFilter = null;' => '$tableFilter = ' . var_export($tables, true) . ';',
        ]);
    }

    public function buildApply(string $sql): string
    {
        return $this->build('remote_db_apply.php', [
            "\$sqlDump = '__BX_CLI_SQL_DUMP__';" => '$sqlDump = ' . var_export($sql, true) . ';',
        ]);
    }

    /** @param array<int, string>|null $tables */
    public function buildWipe(?array $tables): string
    {
        return $this->build('remote_db_wipe.php', [
            '$tableFilter = null;' => '$tableFilter = ' . var_export($tables, true) . ';',
        ]);
    }

    /** @param array<string, string> $replacements */
    private function build(string $filename, array $replacements): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленной операции с базой данных.');
        }

        return $this->withSnippetMixins(strtr($snippet, $replacements));
    }
}
