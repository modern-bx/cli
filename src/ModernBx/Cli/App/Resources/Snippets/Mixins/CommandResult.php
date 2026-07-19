<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

final class CommandResult
{
    public static function success(mixed $result = null): string
    {
        return self::encode([
            'ok' => true,
            'result' => $result,
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function successData(array $data): string
    {
        return self::encode(array_merge(['ok' => true], $data));
    }

    /** @param array<int, mixed> $additionalErrors */
    public static function error(string $error, array $additionalErrors = [], mixed $result = null): string
    {
        $payload = [
            'ok' => false,
            'error' => $error,
        ];

        if ($additionalErrors !== []) {
            $payload['errors'] = $additionalErrors;
        }

        if ($result !== null) {
            $payload['result'] = $result;
        }

        return self::encode($payload);
    }

    /** @param array<string, mixed> $payload */
    private static function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            return '{"ok":false,"error":"Unable to encode command result."}';
        }

        return $json;
    }
}
