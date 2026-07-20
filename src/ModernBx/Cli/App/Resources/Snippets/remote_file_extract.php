<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

$source = $_POST['__remote_file_extract_source']
    ?? json_decode('__REMOTE_FILE_EXTRACT_SOURCE__', true);
$destination = $_POST['__remote_file_extract_destination']
    ?? json_decode('__REMOTE_FILE_EXTRACT_DESTINATION__', true);
$format = $_POST['__remote_file_extract_format']
    ?? json_decode('__REMOTE_FILE_EXTRACT_FORMAT__', true);
$force = $_POST['__remote_file_extract_force']
    ?? json_decode('__REMOTE_FILE_EXTRACT_FORCE__', true);

$result = static function (array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

$normalizeArchivePath = static function (string $path): string {
    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');
    $segments = [];

    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            throw new RuntimeException('Архив содержит путь, выходящий за папку распаковки: ' . $path);
        }

        $segments[] = $segment;
    }

    return implode('/', $segments);
};

try {
    if (!is_string($source) || $source === '' || !is_string($destination) || $destination === '') {
        throw new RuntimeException('Некорректные параметры распаковки.');
    }

    if ($format !== 'zip') {
        throw new RuntimeException('Поддерживаемый формат архива: zip.');
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP-расширение ZipArchive недоступно на удаленном сервере.');
    }

    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($documentRoot === '') {
        throw new RuntimeException('Не удалось определить document root удаленного проекта.');
    }

    $sourcePath = $documentRoot . '/' . ltrim($source, '/');
    $destinationPath = $documentRoot . '/' . ltrim($destination, '/');

    if (!is_file($sourcePath)) {
        throw new RuntimeException('Архив не найден: ' . $source);
    }

    if (file_exists($destinationPath) && !is_dir($destinationPath)) {
        throw new RuntimeException('Путь назначения существует и не является папкой: ' . $destination);
    }

    if (!is_dir($destinationPath) && !mkdir($destinationPath, 0775, true) && !is_dir($destinationPath)) {
        throw new RuntimeException('Не удалось создать папку назначения: ' . $destination);
    }

    $zip = new ZipArchive();
    if ($zip->open($sourcePath) !== true) {
        throw new RuntimeException('Не удалось открыть zip-архив: ' . $source);
    }

    $notices = [];

    try {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name) || $name === '') {
                throw new RuntimeException('Zip-архив содержит запись без имени.');
            }

            $relativePath = $normalizeArchivePath($name);
            if ($relativePath === '') {
                continue;
            }

            $isDirectoryEntry = str_ends_with(str_replace('\\', '/', $name), '/');
            $targetPath = rtrim($destinationPath, '/') . '/' . $relativePath;

            if (!$isDirectoryEntry && is_file($targetPath)) {
                if (!$force) {
                    throw new RuntimeException('Файл уже существует: ' . $destination . '/' . $relativePath);
                }

                $notices[] = 'Файл будет перезаписан: ' . $destination . '/' . $relativePath;
                continue;
            }

            if (!$isDirectoryEntry && is_dir($targetPath)) {
                throw new RuntimeException(
                    'Нельзя распаковать файл поверх папки: ' . $destination . '/' . $relativePath
                );
            }

            if ($isDirectoryEntry && is_file($targetPath)) {
                throw new RuntimeException('Нельзя создать папку поверх файла: ' . $destination . '/' . $relativePath);
            }
        }

        if (!$zip->extractTo($destinationPath)) {
            throw new RuntimeException('Не удалось распаковать zip-архив: ' . $source);
        }
    } finally {
        $zip->close();
    }

    $result(['ok' => true, 'notices' => $notices]);
} catch (Throwable $e) {
    $result(['ok' => false, 'error' => $e->getMessage()]);
}
