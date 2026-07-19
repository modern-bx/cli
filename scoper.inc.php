<?php

/** @return string[] */
$getRemoteSnippetFiles = static function (): array {
    $root = __DIR__;
    $snippetsDir = $root . '/src/ModernBx/Cli/App/Resources/Snippets';

    if (!is_dir($snippetsDir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($snippetsDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    }

    sort($files, SORT_STRING);

    return $files;
};

return [
    'exclude-files' => array_merge([
        "vendor/jakeasmith/http_build_url/src/http_build_url.php",
    ], $getRemoteSnippetFiles()),
    'exclude-namespaces' => [
        '\Bitrix',
    ],
    'exclude-classes' => [
        '\CSite',
    ],
];
