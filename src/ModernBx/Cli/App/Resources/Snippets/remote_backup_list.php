<?php

$send = static function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ok":false}';
};

$findFirstMissingVolume = static function (array $volumes): ?int {
    foreach (array_values($volumes) as $index => $volume) {
        $expected = $index + 1;

        if ((int) $volume !== $expected) {
            return $expected;
        }
    }

    return null;
};

try {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('DOCUMENT_ROOT не определен.');
    }

    $backupDirectory = $documentRoot . '/bitrix/backup';

    if (!is_dir($backupDirectory)) {
        throw new \RuntimeException('Директория резервных копий не найдена: /bitrix/backup');
    }

    $entries = scandir($backupDirectory) ?: [];
    $names = array_values(array_filter(
        $entries,
        static fn (string $entry): bool => $entry !== '.' && $entry !== '..'
    ));
    sort($names, SORT_STRING);
    $items = [];

    foreach ($names as $name) {
        $path = $backupDirectory . '/' . $name;

        if (preg_match('/\.gz$/', $name) !== 1 || !is_file($path)) {
            continue;
        }

        $volumes = [];
        $pattern = '/^' . preg_quote($name, '/') . '\.(\d+)$/';

        foreach ($names as $volumeName) {
            $volumePath = $backupDirectory . '/' . $volumeName;

            if (preg_match($pattern, $volumeName, $matches) !== 1 || !is_file($volumePath)) {
                continue;
            }

            $number = (int) $matches[1];

            if ($number > 0) {
                $volumes[] = $number;
            }
        }

        sort($volumes, SORT_NUMERIC);
        $missingVolume = $findFirstMissingVolume($volumes);
        $items[] = [
            'name' => $name,
            'path' => '/bitrix/backup/' . $name,
            'size' => (int) filesize($path),
            'mtime' => (int) filemtime($path),
            'volumes' => $volumes,
            'incomplete' => $missingVolume !== null,
            'missing_volume' => $missingVolume,
        ];
    }

    $send(['ok' => true, 'result' => $items]);
} catch (\Throwable $err) {
    $send(['ok' => false, 'error' => $err->getMessage()]);
}
