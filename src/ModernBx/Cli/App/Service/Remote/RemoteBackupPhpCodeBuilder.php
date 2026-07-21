<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteBackupPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    public function buildList(): string
    {
        return $this->build('remote_backup_list.php');
    }

    public function buildGetVolumes(string $backupName): string
    {
        return strtr($this->build('remote_backup_get.php'), [
            "'__BX_CLI_BACKUP_NAME__'" => var_export($backupName, true),
        ]);
    }

    public function buildExists(string $filename): string
    {
        return strtr($this->build('remote_backup_exists.php'), [
            "'__BX_CLI_BACKUP_FILENAME__'" => var_export($filename, true),
        ]);
    }

    private function build(string $filename): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException(
                'Не удалось загрузить PHP-сниппет для удаленной операции с резервными копиями.',
            );
        }

        return $this->withSnippetMixins($snippet);
    }
}
