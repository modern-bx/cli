<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

trait RemotePhpTrait
{
    protected RemoteProjectConfigManager $remoteProjectConfigManager;

    protected BitrixAdminClient $bitrixAdminClient;

    /**
     * Выполняет PHP-сниппет через административную PHP-консоль удаленного проекта.
     * При истекшей сессии один раз обновляет PHPSESSID и повторяет запрос.
     */
    protected function executeRemotePhp(string $codename, string $code): string
    {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        try {
            return $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);

            return $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        }
    }

    /**
     * Разбирает стандартный JSON-ответ удаленного сниппета вида {ok,result,error}.
     *
     * @return mixed
     */
    protected function decodeRemoteJsonResult(string $json, string $defaultError): mixed
    {
        $result = json_decode($json, true);

        if (!is_array($result)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный JSON.');
        }

        if (($result['ok'] ?? false) !== true) {
            $error = $result['error'] ?? $defaultError;

            throw new \RuntimeException(is_string($error) ? $error : $defaultError);
        }

        return $result['result'] ?? null;
    }

    /** @param array<string, mixed> $payload */
    protected function buildRemoteSiteCode(string $operation, array $payload): string
    {
        $payloadJson = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encodedPayload = base64_encode($payloadJson);
        $encodedOperation = base64_encode($operation);
        $code = <<<'PHP'
// Сниппет выполняется внутри удаленной административной PHP-консоли Bitrix.
// Используем D7 SiteTable и возвращаем строго JSON, чтобы CLI мог надежно
// отличить успешный результат от ошибки приложения или окружения.
$operation = base64_decode('__OPERATION__', true);
        $payloadJson = base64_decode('__PAYLOAD__', true);

$respond = static function (array $response): void {
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($json)) {
        $json = '{"ok":false,"error":"Не удалось сериализовать ответ удаленной операции site."}';
    }

    echo $json;
};

try {
    if (!is_string($operation) || $operation === '') {
        throw new \RuntimeException('Некорректная удаленная операция site.');
    }

    if (!is_string($payloadJson)) {
        throw new \RuntimeException('Некорректные параметры удаленной операции site.');
    }

    $payload = json_decode($payloadJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
        throw new \RuntimeException('Параметры удаленной операции site не являются корректным JSON-объектом.');
    }

    if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('main')) {
        throw new \RuntimeException('Не удалось подключить модуль main для работы с сайтами.');
    }

    if (!class_exists('\Bitrix\Main\SiteTable')) {
        throw new \RuntimeException('D7-класс Bitrix\\Main\\SiteTable недоступен на удаленном проекте.');
    }

    if ($operation === 'list') {
        $query = $payload['query'] ?? [];
        $flags = (int) ($payload['flags'] ?? JSON_UNESCAPED_UNICODE);

        if (!is_array($query)) {
            throw new \RuntimeException('Параметр query должен быть массивом.');
        }

        $cursor = \Bitrix\Main\SiteTable::getList($query);
        $lines = [];

        while ($site = $cursor->fetch()) {
            $line = json_encode($site, $flags);

            if (!is_string($line)) {
                throw new \RuntimeException('Не удалось сериализовать сайт в JSON.');
            }

            $lines[] = $line;
        }

        $respond(['ok' => true, 'result' => $lines]);
        return;
    }

    if ($operation === 'get') {
        $query = $payload['query'] ?? [];
        $flags = (int) ($payload['flags'] ?? JSON_UNESCAPED_UNICODE);

        if (!is_array($query)) {
            throw new \RuntimeException('Параметр query должен быть массивом.');
        }

        $site = \Bitrix\Main\SiteTable::getList($query)->fetch();
        $line = $site ? json_encode($site, $flags) : null;

        if ($site && !is_string($line)) {
            throw new \RuntimeException('Не удалось сериализовать сайт в JSON.');
        }

        $respond(['ok' => true, 'result' => $line]);
        return;
    }

    if ($operation === 'update') {
        $lid = $payload['lid'] ?? null;
        $fields = $payload['fields'] ?? null;

        if (!is_string($lid) || $lid === '') {
            throw new \RuntimeException('Идентификатор сайта LID должен быть непустой строкой.');
        }

        if (!is_array($fields)) {
            throw new \RuntimeException('Поля сайта должны быть JSON-объектом.');
        }

        $result = \Bitrix\Main\SiteTable::update($lid, $fields);

        if (!$result->isSuccess()) {
            $messages = $result->getErrorMessages();
            throw new \RuntimeException($messages === [] ? 'Не удалось обновить сайт.' : implode(PHP_EOL, $messages));
        }

        $respond(['ok' => true, 'result' => true]);
        return;
    }

    throw new \RuntimeException('Неподдерживаемая удаленная операция site: ' . $operation);
} catch (\Throwable $error) {
    $respond(['ok' => false, 'error' => $error->getMessage()]);
}
PHP;

        return str_replace(
            ['__OPERATION__', '__PAYLOAD__'],
            [$encodedOperation, $encodedPayload],
            $code,
        );
    }
}
