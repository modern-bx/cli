<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteSqlPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_PATH = __DIR__ . '/../../Resources/Snippets/remote_sql_query.php';

    public function build(string $sql, int $page, int $size): string
    {
        $snippet = file_get_contents(self::SNIPPET_PATH);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленного SQL-запроса.');
        }

        $replacements = [
            "\$sqlQuery = '__BX_CLI_SQL_QUERY__';" => '$sqlQuery = ' . var_export($sql, true) . ';',
            '$pageNumber = 1;' => '$pageNumber = ' . $page . ';',
            '$pageSize = 100;' => '$pageSize = ' . $size . ';',
        ];

        $code = strtr($snippet, $replacements);

        return $this->withSnippetMixins($code);
    }
}
