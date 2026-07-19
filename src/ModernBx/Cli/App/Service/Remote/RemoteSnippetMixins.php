<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

trait RemoteSnippetMixins
{
    protected function withSnippetMixins(string $code): string
    {
        $mixins = glob(__DIR__ . '/../../Resources/Snippets/Mixins/*.php');

        if ($mixins === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-миксины для удаленного сниппета.');
        }

        sort($mixins, SORT_STRING);

        $parts = [];

        foreach ($mixins as $mixin) {
            $content = file_get_contents($mixin);

            if ($content === false) {
                throw new \RuntimeException('Не удалось загрузить PHP-миксин для удаленного сниппета.');
            }

            $parts[] = $this->stripPhpOpenTag($content);
        }

        $parts[] = $this->stripPhpOpenTag($code);

        return implode(PHP_EOL . PHP_EOL, $parts);
    }

    protected function stripPhpOpenTag(string $code): string
    {
        return preg_replace('/^<\?php\s*/', '', $code, 1) ?? $code;
    }
}
