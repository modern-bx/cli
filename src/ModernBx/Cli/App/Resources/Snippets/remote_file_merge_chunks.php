<?php

try {
    $destination = $_POST['__remote_file_merge_destination']
        ?? json_decode('__REMOTE_FILE_MERGE_DESTINATION__', true);
    $chunks = $_POST['__remote_file_merge_chunks'] ?? json_decode('__REMOTE_FILE_MERGE_CHUNKS__', true);

    if (!is_string($destination) || $destination === '' || !is_array($chunks) || $chunks === []) {
        throw new \RuntimeException('Некорректные параметры объединения частей файла.');
    }

    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('Не удалось определить document root удаленного проекта.');
    }

    $normalize = static function (string $path): string {
        $path = str_replace('\\', '/', $path);
        $segments = [];

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException('Путь не должен выходить за document root.');
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    };

    $destination = $normalize($destination);
    $destinationPath = $documentRoot . $destination;
    $destinationDirectory = dirname($destinationPath);

    if (!is_dir($destinationDirectory)) {
        throw new \RuntimeException('Директория итогового файла не найдена: ' . dirname($destination));
    }

    $temporaryDestinationPath = $destinationPath . '.merge-' . bin2hex(random_bytes(8));
    $output = fopen($temporaryDestinationPath, 'wb');

    if ($output === false) {
        throw new \RuntimeException('Не удалось создать итоговый файл.');
    }

    try {
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                throw new \RuntimeException('Некорректный путь временной части файла.');
            }

            $chunk = $normalize($chunk);
            $chunkPath = $documentRoot . $chunk;
            $input = fopen($chunkPath, 'rb');

            if ($input === false) {
                throw new \RuntimeException('Не удалось открыть временную часть файла: ' . $chunk);
            }

            try {
                if (stream_copy_to_stream($input, $output) === false) {
                    throw new \RuntimeException('Не удалось записать временную часть файла: ' . $chunk);
                }
            } finally {
                fclose($input);
            }
        }
    } finally {
        fclose($output);
    }

    if (!rename($temporaryDestinationPath, $destinationPath)) {
        @unlink($temporaryDestinationPath);
        throw new \RuntimeException('Не удалось заменить итоговый файл после объединения частей.');
    }

    foreach ($chunks as $chunk) {
        if (is_string($chunk) && $chunk !== '') {
            @unlink($documentRoot . $normalize($chunk));
        }
    }

    // @phpstan-ignore-next-line CommandResult is mixed into remote snippets.
    echo CommandResult::successData(['path' => $destination]);
} catch (\Throwable $err) {
    if (isset($temporaryDestinationPath) && is_string($temporaryDestinationPath)) {
        @unlink($temporaryDestinationPath);
    }

    if (isset($chunks) && is_array($chunks)) {
        foreach ($chunks as $chunk) {
            if (isset($documentRoot, $normalize) && is_string($documentRoot) && is_callable($normalize)
                && is_string($chunk) && $chunk !== ''
            ) {
                try {
                    @unlink($documentRoot . $normalize($chunk));
                } catch (\Throwable $cleanupErr) {
                }
            }
        }
    }

    // @phpstan-ignore-next-line CommandResult is mixed into remote snippets.
    echo CommandResult::error($err->getMessage());
}
