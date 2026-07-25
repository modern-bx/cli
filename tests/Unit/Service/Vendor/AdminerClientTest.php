<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Service\Vendor;

use ModernBx\Cli\App\Service\Vendor\AdminerClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminerClientTest extends TestCase
{
    public function testParsesTheadAndTbodyFromImportResponse(): void
    {
        $html = <<<'HTML'
            <html><table class="checkable">
            <thead><tr><th>Query</th><th>Result</th></tr></thead>
            <tbody>
                <tr><td>CREATE TABLE test</td><td>OK<br>0.01 s</td></tr>
                <tr><td>INSERT</td><td>2 rows</td></tr>
            </tbody>
            </table></html>
            HTML;

        self::assertSame([
            'columns' => ['Query', 'Result'],
            'rows' => [
                ['CREATE TABLE test', "OK\n0.01 s"],
                ['INSERT', '2 rows'],
            ],
        ], $this->invoke('parseResultTable', [$html]));
    }

    public function testImportBodyRunsServerFileAndContainsToken(): void
    {
        $body = $this->invoke('buildImportBody', ['boundary', '176844:271827']);

        self::assertIsString($body);
        self::assertStringContainsString('name="sql_file[]"; filename=""', $body);
        self::assertStringContainsString("name=\"webfile\"\r\n\r\nRun file", $body);
        self::assertStringContainsString("name=\"token\"\r\n\r\n176844:271827", $body);
        self::assertStringEndsWith("--boundary--\r\n", $body);
    }

    public function testExtractsAndDecodesToken(): void
    {
        self::assertSame(
            '176844:271827',
            $this->invoke('extractToken', ['<input name="token" value="176844&#58;271827">']),
        );
    }

    public function testUsesCanonicalDatabaseUrlFromLoginRedirect(): void
    {
        self::assertSame(
            'http://example.test/adminer.php?server=mysql&username=user&db=database&adminer_sid=session',
            $this->invoke('resolveDatabaseUrl', [
                'http://example.test/adminer.php',
                ['HTTP/1.1 302 Found', 'Location: ?server=mysql&username=user&db=database&adminer_sid=session'],
                'mysql',
                'mysql',
                'user',
                'database',
            ]),
        );
    }

    public function testBuildsPostgresqlDatabaseUrlWithoutRedirect(): void
    {
        self::assertSame(
            'http://example.test/adminer.php?pgsql=postgres&username=user&db=database',
            $this->invoke('resolveDatabaseUrl', [
                'http://example.test/adminer.php',
                [],
                'pgsql',
                'postgres',
                'user',
                'database',
            ]),
        );
    }

    /** @param mixed[] $arguments */
    private function invoke(string $methodName, array $arguments): mixed
    {
        $reflection = new ReflectionClass(AdminerClient::class);
        $client = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($client, $arguments);
    }
}
