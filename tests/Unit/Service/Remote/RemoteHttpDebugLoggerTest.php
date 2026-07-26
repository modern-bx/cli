<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Service\Remote;

use ModernBx\Cli\App\Service\Remote\RemoteHttpDebugLogger;
use PHPUnit\Framework\TestCase;

final class RemoteHttpDebugLoggerTest extends TestCase
{
    public function testWritesExchangeAndRedactsAuthenticationHeaders(): void
    {
        $home = sys_get_temp_dir() . '/bx-cli-http-log-' . bin2hex(random_bytes(6));

        try {
            $logger = new RemoteHttpDebugLogger($home);
            $logger->write(
                'POST',
                'https://example.com/bitrix/admin/',
                ['Cookie: PHPSESSID=secret-session', 'User-Agent: bx-cli remote'],
                'USER_LOGIN=admin&USER_PASSWORD=%5BREDACTED%5D',
                200,
                ['HTTP/1.1 200 OK', 'Set-Cookie: PHPSESSID=secret-session'],
                '<html>response body</html>',
            );

            $contents = file_get_contents($logger->getPath());
            self::assertIsString($contents);
            self::assertStringContainsString('POST https://example.com/bitrix/admin/', $contents);
            self::assertStringContainsString('HTTP status: 200', $contents);
            self::assertStringContainsString('<html>response body</html>', $contents);
            self::assertStringContainsString('Cookie: [REDACTED]', $contents);
            self::assertStringNotContainsString('secret-session', $contents);
            self::assertSame(0600, fileperms($logger->getPath()) & 0777);
        } finally {
            $this->removeDirectory($home);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
