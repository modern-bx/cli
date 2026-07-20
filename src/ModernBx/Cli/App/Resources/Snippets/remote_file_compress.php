<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

$source = $_POST['__remote_file_compress_source']
    ?? json_decode('__REMOTE_FILE_COMPRESS_SOURCE__', true);
$type = $_POST['__remote_file_compress_type']
    ?? json_decode('__REMOTE_FILE_COMPRESS_TYPE__', true);

$result = static function (array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

try {
    if (!is_string($source) || $source === '' || !is_string($type) || $type === '') {
        throw new RuntimeException('Некорректные параметры сжатия.');
    }

    if ($type !== 'zip') {
        throw new RuntimeException('Неподдерживаемый тип сжатия: ' . $type);
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP-расширение ZipArchive недоступно на удаленном сервере.');
    }

    $documentRoot = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/');
    if ($documentRoot === '') {
        throw new RuntimeException('Не удалось определить document root удаленного проекта.');
    }

    $sourcePath = $documentRoot . '/' . ltrim($source, '/');
    if (!is_file($sourcePath) && !is_dir($sourcePath)) {
        throw new RuntimeException('Файл или папка не найдены: ' . $source);
    }

    $baseDir = $documentRoot . '/bitrix/tmp/bx-cli/compress/'
        . date('Ymd') . '/' . str_replace('.', '', uniqid('', true));
    if (!is_dir($baseDir) && !mkdir($baseDir, 0700, true) && !is_dir($baseDir)) {
        throw new RuntimeException('Не удалось создать временную папку для архива.');
    }

    $archiveName = basename($sourcePath);
    if ($archiveName === '' || $archiveName === '/' || $archiveName === '.') {
        $archiveName = 'archive';
    }

    $archivePath = $baseDir . '/' . $archiveName . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Не удалось создать zip-архив.');
    }

    try {
        if (is_file($sourcePath)) {
            if (!$zip->addFile($sourcePath, basename($sourcePath))) {
                throw new RuntimeException('Не удалось добавить файл в zip-архив.');
            }
        } else {
            $rootName = basename($sourcePath);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $zip->addEmptyDir($rootName);
            foreach ($iterator as $item) {
                if (!$item instanceof SplFileInfo) {
                    continue;
                }

                $itemPath = $item->getPathname();
                $relativePath = $rootName . '/' . ltrim(substr($itemPath, strlen($sourcePath)), '/\\');
                $relativePath = str_replace('\\', '/', $relativePath);

                if ($item->isDir()) {
                    $zip->addEmptyDir($relativePath);
                    continue;
                }

                if ($item->isFile() && !$zip->addFile($itemPath, $relativePath)) {
                    throw new RuntimeException('Не удалось добавить файл в zip-архив: ' . $relativePath);
                }
            }
        }
    } finally {
        $zip->close();
    }

    $remotePath = substr($archivePath, strlen($documentRoot));
    $result(['ok' => true, 'path' => $remotePath]);
} catch (Throwable $e) {
    $result(['ok' => false, 'error' => $e->getMessage()]);
}
