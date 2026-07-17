<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class BitrixAdminClient
{
    /**
     * @return array{value: string, expires: string}
     */
    public function login(string $endpoint, string $login, string $password): array
    {
        $response = $this->post($endpoint . '/bitrix/admin/', [
            'AUTH_FORM' => 'Y',
            'TYPE' => 'AUTH',
            'backurl' => '/bitrix/admin/',
            'USER_LOGIN' => $login,
            'USER_PASSWORD' => $password,
            'Login' => 'Y',
        ]);

        if ($response['status'] < 200 || $response['status'] >= 400) {
            throw new \RuntimeException('Не удалось авторизоваться в админке проекта.');
        }

        $cookie = $this->extractPhpSessionIdCookie($response['headers']);

        if ($cookie === null) {
            throw new \RuntimeException('Авторизация не вернула PHPSESSID.');
        }

        if ($response['status'] < 300 && $this->looksLikeLoginForm($response['body'])) {
            throw new \RuntimeException('Неверный логин или пароль администратора.');
        }

        return $cookie;
    }

    public function executePhp(string $endpoint, string $sessionId, string $code): string
    {
        $sessid = $this->getPhpConsoleSessid($endpoint, $sessionId);
        $consoleUrl = $endpoint . '/bitrix/admin/php_command_line.php';
        $response = $this->post($consoleUrl . '?lang=ru&sessid=' . rawurlencode($sessid), [
            'query' => $code,
            'result_as_text' => 'y',
            'ajax' => 'y',
        ], $this->getPhpConsoleHeaders($endpoint, $sessionId));

        if ($response['status'] === 401
            || $response['status'] === 403
            || $this->looksLikeLoginForm($response['body'])
        ) {
            throw new \RuntimeException('REMOTE_SESSION_EXPIRED');
        }

        if (($response['status'] < 200 || $response['status'] >= 400)
            && !$this->hasPhpConsoleResult($response['body'])
        ) {
            throw new \RuntimeException('Не удалось выполнить удаленный PHP-код.');
        }

        return $this->sanitizePhpConsoleResult($response['body']);
    }


    protected function getPhpConsoleSessid(string $endpoint, string $sessionId): string
    {
        $consoleUrl = $endpoint . '/bitrix/admin/php_command_line.php?lang=ru';
        $response = $this->get($consoleUrl, $this->getPhpConsoleHeaders($endpoint, $sessionId));

        if ($response['status'] === 401
            || $response['status'] === 403
            || $this->looksLikeLoginForm($response['body'])
        ) {
            throw new \RuntimeException('REMOTE_SESSION_EXPIRED');
        }

        if ($response['status'] < 200 || $response['status'] >= 400) {
            throw new \RuntimeException('Не удалось открыть удаленную PHP-консоль.');
        }

        if (preg_match('/[?&]sessid=([a-f0-9]{32})/i', $response['body'], $matches)) {
            return $matches[1];
        }

        if (preg_match('/name=["\']sessid["\'][^>]+value=["\']([^"\']+)/i', $response['body'], $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/bitrix_sessid\(\)\s*=\s*["\']([^"\']+)/i', $response['body'], $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        throw new \RuntimeException('Не удалось получить sessid удаленной PHP-консоли.');
    }

    /**
     * @return string[]
     */
    protected function getPhpConsoleHeaders(string $endpoint, string $sessionId): array
    {
        return [
            'Accept: */*',
            'Bx-ajax: true',
            'Origin: ' . $endpoint,
            'Referer: ' . $endpoint . '/bitrix/admin/php_command_line.php?lang=ru',
            'Cookie: PHPSESSID=' . $sessionId,
        ];
    }


    /**
     * @param string[] $extraHeaders
     * @return array{status: int, headers: string[], body: string}
     */
    protected function get(string $url, array $extraHeaders = []): array
    {
        $headers = array_merge([
            'User-Agent: bx-cli remote',
        ], $extraHeaders);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header;
        $status = $this->getStatusCode($responseHeaders);

        if ($response === false || $status === null) {
            throw new \RuntimeException('Не удалось выполнить HTTP-запрос к удаленному проекту.');
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $response,
        ];
    }

    /**
     * @param array<string, string> $data
     * @param string[] $extraHeaders
     * @return array{status: int, headers: string[], body: string}
     */
    protected function post(string $url, array $data, array $extraHeaders = []): array
    {
        $body = http_build_query($data);
        $headers = array_merge([
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($body),
            'User-Agent: bx-cli remote',
        ], $extraHeaders);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header;
        $status = $this->getStatusCode($responseHeaders);

        if ($response === false || $status === null) {
            throw new \RuntimeException('Не удалось выполнить HTTP-запрос к удаленному проекту.');
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $response,
        ];
    }

    /**
     * @param string[] $headers
     */
    protected function getStatusCode(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    protected function looksLikeLoginForm(string $response): bool
    {
        return str_contains($response, 'name="AUTH_FORM"')
            || str_contains($response, "name='AUTH_FORM'")
            || str_contains($response, 'name="USER_LOGIN"')
            || str_contains($response, "name='USER_LOGIN'");
    }

    /**
     * @param string[] $headers
     * @return array{value: string, expires: string}|null
     */
    protected function extractPhpSessionIdCookie(array $headers): ?array
    {
        foreach ($headers as $header) {
            if (!preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]+)/i', $header, $matches)) {
                continue;
            }

            return [
                'value' => urldecode($matches[1]),
                'expires' => $this->extractCookieExpires($header),
            ];
        }

        return null;
    }

    protected function extractCookieExpires(string $setCookieHeader): string
    {
        if (preg_match('/;\s*expires=([^;]+)/i', $setCookieHeader, $matches)) {
            $timestamp = strtotime($matches[1]);

            if ($timestamp !== false) {
                return date(DATE_ATOM, $timestamp);
            }
        }

        if (preg_match('/;\s*max-age=(\d+)/i', $setCookieHeader, $matches)) {
            return date(DATE_ATOM, time() + (int) $matches[1]);
        }

        return date(DATE_ATOM, time() + (int) ini_get('session.gc_maxlifetime'));
    }

    protected function hasPhpConsoleResult(string $body): bool
    {
        return (bool) preg_match('/<pre[^>]*>.*?<\/pre>/is', $body);
    }

    protected function sanitizePhpConsoleResult(string $body): string
    {
        if (preg_match('/<pre[^>]*>(.*?)<\/pre>/is', $body, $matches)) {
            $body = $matches[1];
        }

        $body = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $body) ?? $body;
        $body = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $body) ?? $body;
        $body = strip_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace('/\R{3,}/', "\n\n", $body) ?? $body;

        return trim($body);
    }
}
