<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Vendor;

final class AdminerClient
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) bx-cli/adminer-import';

    /** @var array<string, string> */
    private array $cookies = [];

    /**
     * @return array{columns: string[], rows: array<int, array<int, string>>}
     */
    public function import(
        string $endpoint,
        string $adminerPath,
        string $basicAuthPassword,
        string $engine,
        string $database,
        string $dbHost,
        string $dbUser,
        string $dbPassword,
        ?callable $progress = null
    ): array {
        $adminerUrl = rtrim($endpoint, '/') . '/' . ltrim($adminerPath, '/');
        $authorization = 'Authorization: Basic ' . base64_encode('admin:' . $basicAuthPassword);
        $commonHeaders = [$authorization, 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'];

        $loginPage = $this->request('GET', $adminerUrl, $commonHeaders);
        $this->assertSuccessful($loginPage, 'Не удалось открыть Adminer. Проверьте пароль HTTP-авторизации.');
        $progress && $progress('HTTP-авторизация Adminer пройдена.');

        $login = $this->request('POST', $adminerUrl, array_merge($commonHeaders, [
            'Content-Type: application/x-www-form-urlencoded',
            'Referer: ' . $adminerUrl,
            'Origin: ' . rtrim($endpoint, '/'),
        ]), http_build_query([
            'auth' => [
                'driver' => $engine === 'mysql' ? 'server' : 'pgsql',
                'server' => $dbHost,
                'username' => $dbUser,
                'password' => $dbPassword,
                'db' => $database,
                'permanent' => '1',
            ],
        ]));
        $this->assertSuccessful($login, 'Не удалось войти в базу данных через Adminer.');
        if ($login['status'] < 300 && $this->looksLikeLoginPage($login['body'])) {
            throw new \RuntimeException('Неверные параметры подключения к базе данных.');
        }
        $progress && $progress('Вход в базу данных через Adminer выполнен.');

        $databaseUrl = $this->resolveDatabaseUrl(
            $adminerUrl,
            $login['headers'],
            $engine,
            $dbHost,
            $dbUser,
            $database,
        );
        $importUrl = $databaseUrl . (str_contains($databaseUrl, '?') ? '&' : '?') . 'import=';
        $importPage = $this->request('GET', $importUrl, array_merge($commonHeaders, ['Referer: ' . $adminerUrl]));
        $this->assertImportPageSuccessful($importPage);
        $token = $this->extractToken($importPage['body']);
        $progress && $progress('Страница импорта открыта, cookie и token собраны.');

        $boundary = '----bxcliformboundary' . bin2hex(random_bytes(16));
        $body = $this->buildImportBody($boundary, $token);
        $response = $this->request('POST', $importUrl, array_merge($commonHeaders, [
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . strlen($body),
            'Referer: ' . $importUrl,
            'Origin: ' . rtrim($endpoint, '/'),
        ]), $body);
        $this->assertSuccessful($response, 'Не удалось запустить импорт через Adminer.');
        $progress && $progress('Запрос импорта выполнен, ответ Adminer получен.');

        return $this->parseResultTable($response['body']);
    }

    /**
     * @param string[] $headers
     * @return array{status: int, headers: string[], body: string}
     */
    private function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        if ($this->cookies !== []) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(
                static fn (string $name, string $value): string => $name . '=' . $value,
                array_keys($this->cookies),
                $this->cookies,
            ));
        }
        $headers[] = 'User-Agent: ' . self::USER_AGENT;
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 300,
        ]]);
        $responseBody = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header;
        if (!is_string($responseBody)) {
            throw new \RuntimeException('Не удалось выполнить HTTP-запрос к Adminer.');
        }
        $this->collectCookies($responseHeaders);

        return [
            'status' => $this->statusCode($responseHeaders),
            'headers' => $responseHeaders,
            'body' => $responseBody,
        ];
    }

    /** @param string[] $headers */
    private function collectCookies(array $headers): void
    {
        foreach ($headers as $header) {
            if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $header, $matches)) {
                $this->cookies[$matches[1]] = $matches[2];
            }
        }
    }

    /** @param string[] $headers */
    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }
        throw new \RuntimeException('Adminer не вернул HTTP-статус.');
    }

    /** @param array{status: int, headers: string[], body: string} $response */
    private function assertSuccessful(array $response, string $message): void
    {
        if ($response['status'] < 200 || $response['status'] >= 400) {
            throw new \RuntimeException($message . ' HTTP ' . $response['status'] . '.');
        }
    }

    private function looksLikeLoginPage(string $body): bool
    {
        return preg_match('/name=["\']auth\[(?:username|password)\]["\']/i', $body) === 1;
    }

    /**
     * @param string[] $headers
     */
    private function resolveDatabaseUrl(
        string $adminerUrl,
        array $headers,
        string $engine,
        string $dbHost,
        string $dbUser,
        string $database
    ): string {
        $location = $this->findHeader($headers, 'Location');
        if ($location !== null) {
            if (preg_match('#^https?://#i', $location)) {
                return $location;
            }
            if (str_starts_with($location, '?')) {
                return $adminerUrl . $location;
            }
            if (str_starts_with($location, '/')) {
                $parts = parse_url($adminerUrl);
                if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                    $origin = $parts['scheme'] . '://' . $parts['host'];
                    if (isset($parts['port'])) {
                        $origin .= ':' . $parts['port'];
                    }
                    return $origin . $location;
                }
            }
            return rtrim(dirname($adminerUrl), '/') . '/' . ltrim($location, '/');
        }

        $serverParameter = $engine === 'pgsql' ? 'pgsql' : 'server';
        return $adminerUrl . '?' . http_build_query([
            $serverParameter => $dbHost,
            'username' => $dbUser,
            'db' => $database,
        ]);
    }

    /** @param string[] $headers */
    private function findHeader(array $headers, string $name): ?string
    {
        $value = null;
        foreach ($headers as $header) {
            if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/i', $header, $matches)) {
                $value = trim($matches[1]);
            }
        }
        return $value;
    }

    /** @param array{status: int, headers: string[], body: string} $response */
    private function assertImportPageSuccessful(array $response): void
    {
        if ($response['status'] === 403 && $this->looksLikeLoginPage($response['body'])) {
            throw new \RuntimeException(
                'Авторизация в базе данных Adminer не сохранилась. '
                . 'Проверьте --db-engine, --db-host, --db-user и --db-password.',
            );
        }
        $this->assertSuccessful($response, 'Не удалось открыть страницу импорта Adminer.');
    }

    private function extractToken(string $body): string
    {
        if (!preg_match('/name=["\']token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $matches)) {
            throw new \RuntimeException('Не удалось получить token со страницы импорта Adminer.');
        }
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function buildImportBody(string $boundary, string $token): string
    {
        $parts = [
            ['sql_file[]', '', ''],
            ['webfile', 'Run file', null],
            ['error_stops', '1', null],
            ['only_errors', '1', null],
            ['token', $token, null],
        ];
        $body = '';
        foreach ($parts as [$name, $value, $filename]) {
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"{$name}\"";
            if ($filename !== null) {
                $body .= '; filename=""' . "\r\nContent-Type: application/octet-stream";
            }
            $body .= "\r\n\r\n{$value}\r\n";
        }
        return $body . '--' . $boundary . "--\r\n";
    }

    /** @return array{columns: string[], rows: array<int, array<int, string>>} */
    private function parseResultTable(string $body): array
    {
        if (!preg_match('/<table\b[^>]*>(.*?)<\/table>/is', $body, $table)) {
            throw new \RuntimeException('Adminer не вернул таблицу с результатом импорта.');
        }
        preg_match('/<thead\b[^>]*>(.*?)<\/thead>/is', $table[1], $head);
        preg_match_all('/<t[hd]\b[^>]*>(.*?)<\/t[hd]>/is', $head[1] ?? '', $headers);
        preg_match('/<tbody\b[^>]*>(.*?)<\/tbody>/is', $table[1], $bodyRows);
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $bodyRows[1] ?? '', $rowMatches);
        $rows = [];
        foreach ($rowMatches[1] as $row) {
            preg_match_all('/<t[hd]\b[^>]*>(.*?)<\/t[hd]>/is', $row, $cells);
            $rows[] = array_map([$this, 'cleanCell'], $cells[1]);
        }
        return ['columns' => array_map([$this, 'cleanCell'], $headers[1]), 'rows' => $rows];
    }

    private function cleanCell(string $cell): string
    {
        $cell = preg_replace('/<br\s*\/?\s*>/i', "\n", $cell) ?? $cell;
        return trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
