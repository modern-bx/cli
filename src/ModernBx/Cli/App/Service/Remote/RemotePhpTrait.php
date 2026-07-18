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
}
