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


    /**
     * @return array{columns: string[], rows: array<int, array<int, string>>}
     */
    public function executeSql(string $endpoint, string $sessionId, string $sql, ?int $page, int $size): array
    {
        $sessid = $this->getAdminPageSessid(
            $endpoint,
            $sessionId,
            '/bitrix/admin/sql.php?lang=ru&del_query=Y',
        );
        $url = $endpoint . '/bitrix/admin/sql.php?mode=frame&lang=ru&del_query=Y';

        if ($page !== null) {
            $url .= '&PAGEN_1=' . $page;
        }

        $url .= '&SIZEN_1=' . $size;
        $response = $this->post($url, [
            'sessid' => $sessid,
            'query' => $sql,
            'save' => 'Y',
        ], $this->getSqlHeaders($endpoint, $sessionId));

        if ($response['status'] === 401
            || $response['status'] === 403
            || $this->looksLikeLoginForm($response['body'])
        ) {
            throw new \RuntimeException('REMOTE_SESSION_EXPIRED');
        }

        $sqlError = $this->extractSqlError($response['body']);

        if ($sqlError !== null) {
            throw new \RuntimeException($sqlError, 1);
        }

        if (($response['status'] < 200 || $response['status'] >= 400)
            && !$this->hasSqlResult($response['body'])
        ) {
            throw new \RuntimeException('Не удалось выполнить удаленный SQL-запрос.');
        }

        return $this->parseSqlResult($response['body']);
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

    /**
     * @return array{columns: string[], rows: array<int, array<int, string>>}
     */
    public function executeSqlPhp(string $endpoint, string $sessionId, string $code): array
    {
        $json = $this->executePhp($endpoint, $sessionId, $code);
        $result = json_decode($json, true);

        if (!is_array($result)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный JSON с результатом SQL-запроса.');
        }

        if (($result['ok'] ?? false) !== true) {
            $error = $result['error'] ?? 'Не удалось выполнить удаленный SQL-запрос через PHP-консоль.';
            $message = is_string($error)
                ? $error
                : 'Не удалось выполнить удаленный SQL-запрос через PHP-консоль.';

            throw new \RuntimeException($message, 1);
        }

        $columns = $result['columns'] ?? [];
        $rows = $result['rows'] ?? [];

        if (!is_array($columns) || !is_array($rows)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректную структуру результата SQL-запроса.');
        }

        return [
            'columns' => array_values(array_map([$this, 'stringifyRemoteSqlValue'], $columns)),
            'rows' => array_values(array_map(
                fn (mixed $row): array => is_array($row)
                    ? array_values(array_map([$this, 'stringifyRemoteSqlValue'], $row))
                    : [],
                $rows,
            )),
        ];
    }

    protected function stringifyRemoteSqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }


    protected function getAdminPageSessid(string $endpoint, string $sessionId, string $path): string
    {
        $response = $this->get($endpoint . $path, $this->getSqlHeaders($endpoint, $sessionId));

        if ($response['status'] === 401
            || $response['status'] === 403
            || $this->looksLikeLoginForm($response['body'])
        ) {
            throw new \RuntimeException('REMOTE_SESSION_EXPIRED');
        }

        if ($response['status'] < 200 || $response['status'] >= 400) {
            throw new \RuntimeException('Не удалось открыть страницу администрирования удаленного проекта.');
        }

        return $this->extractSessid($response['body']);
    }

    protected function extractSessid(string $body): string
    {
        if (preg_match('/[?&]sessid=([a-f0-9]{32})/i', $body, $matches)) {
            return $matches[1];
        }

        if (preg_match('/name=["\']sessid["\'][^>]+value=["\']([^"\']+)/i', $body, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/bitrix_sessid\(\)\s*=\s*["\']([^"\']+)/i', $body, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        throw new \RuntimeException('Не удалось получить sessid удаленной админки.');
    }

    /**
     * @return string[]
     */
    protected function getSqlHeaders(string $endpoint, string $sessionId): array
    {
        return [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Origin: ' . $endpoint,
            'Referer: ' . $endpoint . '/bitrix/admin/sql.php?lang=ru&del_query=Y',
            'Cookie: PHPSESSID=' . $sessionId,
        ];
    }


    protected function extractSqlError(string $body): ?string
    {
        $errorPattern = '/<div[^>]+class=["\'][^"\']*adm-info-message-wrap[^"\']*'
            . 'adm-info-message-red[^"\']*["\'][^>]*>(.*?)<\/div>\s*'
            . '<div[^>]+class=["\'][^"\']*adm-list-table-wrap/is';

        if (!preg_match($errorPattern, $body, $matches)) {
            return null;
        }

        $message = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $matches[1]) ?? $matches[1];
        $message = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $message) ?? $message;
        $message = preg_replace('/<\/div>/i', "\n", $message) ?? $message;
        $message = preg_replace('/<br\s*\/?\s*>/i', "\n", $message) ?? $message;
        $message = strip_tags($message);
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = str_replace('\\n', "\n", $message);
        $message = preg_replace('/[ \t]+/', ' ', $message) ?? $message;
        $message = preg_replace('/\h*\R\h*/', "\n", $message) ?? $message;
        $lines = array_filter(
            array_map(static fn (string $line): string => trim($line), explode("\n", $message)),
            static fn (string $line): bool => !in_array($line, [
                '',
                'Ошибка',
                'Ошибка во время выполнения запроса:',
            ], true),
        );
        $message = trim(implode("\n", $lines));

        return $message !== '' ? $message : null;
    }

    protected function hasSqlResult(string $body): bool
    {
        return str_contains($body, 'id="tbl_sql"') || str_contains($body, "id='tbl_sql'");
    }

    /**
     * @return array{columns: string[], rows: array<int, array<int, string>>}
     */
    protected function parseSqlResult(string $body): array
    {
        if (!preg_match('/<table[^>]+id=["\']tbl_sql["\'][^>]*>(.*?)<\/table>/is', $body, $tableMatches)) {
            return ['columns' => [], 'rows' => []];
        }

        $table = $tableMatches[1];
        preg_match_all(
            '/<div[^>]+class=["\'][^"\']*adm-list-table-cell-inner[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            $table,
            $headerMatches,
        );
        $columns = array_map(fn (string $value): string => $this->cleanSqlCell($value), $headerMatches[1]);
        preg_match_all(
            '/<tr[^>]+class=["\'][^"\']*adm-list-table-row[^"\']*["\'][^>]*>(.*?)<\/tr>/is',
            $table,
            $rowMatches,
        );
        $rows = [];

        foreach ($rowMatches[1] as $rowHtml) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowHtml, $cellMatches);
            $rows[] = array_map(fn (string $value): string => $this->cleanSqlCell($value), $cellMatches[1]);
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    protected function cleanSqlCell(string $value): string
    {
        $value = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $value) ?? $value;
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xc2\xa0", '', $value);

        return trim($value);
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

        return $this->extractSessid($response['body']);
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
        $body = strip_tags($body);
        $body = preg_replace('/\R{3,}/', "\n\n", $body) ?? $body;

        return trim($body);
    }
}
