<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteModulePhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET_DIR = __DIR__ . '/../../Resources/Snippets';

    public function buildInstall(string $moduleCode): string
    {
        return $this->build('remote_module_install.php', $moduleCode);
    }

    public function buildUninstall(string $moduleCode): string
    {
        return $this->build('remote_module_uninstall.php', $moduleCode);
    }

    /** @param string[] $moduleCodes */
    public function buildVersion(array $moduleCodes): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/remote_module_version.php');

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленной операции с модулем.');
        }

        $code = strtr($snippet, [
            '$moduleCodes = [];' => '$moduleCodes = ' . var_export(array_values($moduleCodes), true) . ';',
        ]);

        return $this->withSnippetMixins($code);
    }

    private function build(string $filename, string $moduleCode): string
    {
        $snippet = file_get_contents(self::SNIPPET_DIR . '/' . $filename);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для удаленной операции с модулем.');
        }

        $code = strtr($snippet, [
            "\$moduleCode = '__BX_CLI_MODULE_CODE__';" => '$moduleCode = ' . var_export($moduleCode, true) . ';',
        ]);

        return $this->withSnippetMixins($code);
    }
}
