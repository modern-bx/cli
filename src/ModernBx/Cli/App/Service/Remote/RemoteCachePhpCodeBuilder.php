<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteCachePhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_PATH = __DIR__ . '/../../Resources/Snippets/remote_cache_clear.php';

    /** @param string[] $directories */
    public function build(array $directories): string
    {
        $snippet = file_get_contents(self::SNIPPET_PATH);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленной очистки кеша.');
        }

        $code = strtr($snippet, [
            '$directories = [\'cache\', \'managed_cache\', \'stack_cache\'];' => '$directories = '
                . var_export(array_values($directories), true)
                . ';',
        ]);

        return $this->withSnippetMixins($code);
    }
}
