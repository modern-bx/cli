<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteFileApplyPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    /**
     * @param string[] $directories
     * @param array<int, array{relative: string, size: int}> $files
     */
    public function buildDiagnose(string $dest, array $directories, array $files, bool $force): string
    {
        return $this->build('remote_file_apply_diagnose.php', [
            '$payload = [];' => '$payload = ' . var_export([
                'dest' => $dest,
                'directories' => array_values($directories),
                'files' => array_values($files),
                'force' => $force,
            ], true) . ';',
        ]);
    }

    /** @param string[] $directories */
    public function buildCreateDirectories(string $dest, array $directories): string
    {
        return $this->build('remote_file_apply_create_dirs.php', [
            '$payload = [];' => '$payload = ' . var_export([
                'dest' => $dest,
                'directories' => array_values($directories),
            ], true) . ';',
        ]);
    }

    /** @param array<string, string> $replacements */
    private function build(string $filename, array $replacements): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленного file:apply.');
        }

        return $this->withSnippetMixins(strtr($snippet, $replacements));
    }
}
