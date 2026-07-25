<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteConfigPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_PATH = __DIR__ . '/../../Resources/Snippets/remote_config_get.php';

    /** @param string[] $parameters */
    public function build(array $parameters): string
    {
        $snippet = file_get_contents(self::SNIPPET_PATH);
        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет получения конфигурации remote.');
        }

        return strtr($this->withSnippetMixins($snippet), [
            '$parameters = [];' => '$parameters = ' . var_export(array_values($parameters), true) . ';',
        ]);
    }
}
