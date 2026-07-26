<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteHttpDebugLogger
{
    private string $path;

    public function __construct(?string $home = null)
    {
        $home = $home ?? ($_SERVER['HOME'] ?? getenv('HOME'));

        if (!is_string($home) || trim($home) === '') {
            throw new \RuntimeException('Не удалось определить домашнюю директорию для HTTP-лога.');
        }

        $directory = rtrim($home, '/') . '/.config/bx-cli/logs';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Не удалось создать директорию логов: %s', $directory));
        }

        $this->path = $directory . '/remote-register-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.log';
        if (file_put_contents($this->path, '') === false || !chmod($this->path, 0600)) {
            throw new \RuntimeException(sprintf('Не удалось создать HTTP-лог: %s', $this->path));
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string[] $requestHeaders
     * @param string[] $responseHeaders
     */
    public function write(
        string $method,
        string $url,
        array $requestHeaders,
        string $requestBody,
        int $status,
        array $responseHeaders,
        string $responseBody
    ): void {
        $entry = sprintf(
            "[%s] %s %s\n\n> %s\n\n%s\n\n< HTTP status: %d\n< %s\n\n%s\n\n%s\n",
            date(DATE_ATOM),
            $method,
            $url,
            implode("\n> ", $this->redactHeaders($requestHeaders)),
            $requestBody,
            $status,
            implode("\n< ", $this->redactHeaders($responseHeaders)),
            $responseBody,
            str_repeat('=', 80),
        );

        if (file_put_contents($this->path, $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Не удалось записать HTTP-лог: %s', $this->path));
        }
    }

    /**
     * @param string[] $headers
     * @return string[]
     */
    private function redactHeaders(array $headers): array
    {
        return array_map(static function (string $header): string {
            if (preg_match('/^(Authorization):/i', $header, $matches)) {
                return $matches[1] . ': [REDACTED]';
            }

            return $header;
        }, $headers);
    }
}
