<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteFilePhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    public function buildUploadLimits(): string
    {
        return $this->build('remote_file_upload_limits.php');
    }

    public function buildCompress(string $source, string $type): string
    {
        return strtr($this->build('remote_file_compress.php'), [
            "'__REMOTE_FILE_COMPRESS_SOURCE__'" => var_export(json_encode($source), true),
            "'__REMOTE_FILE_COMPRESS_TYPE__'" => var_export(json_encode($type), true),
        ]);
    }

    public function buildDelete(string $path): string
    {
        return strtr($this->build('remote_file_delete.php'), [
            "'__REMOTE_FILE_DELETE_PATH__'" => var_export(json_encode($path), true),
        ]);
    }

    /** @param string[] $chunks */
    public function buildMergeChunks(string $destination, array $chunks): string
    {
        return strtr($this->build('remote_file_merge_chunks.php'), [
            "'__REMOTE_FILE_MERGE_DESTINATION__'" => var_export(json_encode($destination), true),
            "'__REMOTE_FILE_MERGE_CHUNKS__'" => var_export(json_encode(array_values($chunks)), true),
        ]);
    }

    private function build(string $filename): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленной файловой операции.');
        }

        return $this->withSnippetMixins($snippet);
    }
}
