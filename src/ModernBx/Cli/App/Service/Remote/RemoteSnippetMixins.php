<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

trait RemoteSnippetMixins
{
    protected function withSnippetMixins(string $code): string
    {
        $mixins = $this->getSnippetMixinFiles();
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

    /** @return string[] */
    protected function getSnippetMixinFiles(): array
    {
        $mixinsDir = __DIR__ . '/../../Resources/Snippets/Mixins';

        if (!is_dir($mixinsDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($mixinsDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files, SORT_STRING);

        return $files;
    }

    protected function stripPhpOpenTag(string $code): string
    {
        return preg_replace('/^<\?php\s*/', '', $code, 1) ?? $code;
    }
}
