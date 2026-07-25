<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Service\Remote;

use ModernBx\Cli\App\Service\Remote\RemoteConfigPhpCodeBuilder;
use PHPUnit\Framework\TestCase;

final class RemoteConfigPhpCodeBuilderTest extends TestCase
{
    public function testEmbedsParametersInOriginalOrder(): void
    {
        $code = (new RemoteConfigPhpCodeBuilder())->build(['db.password', 'db.engine']);

        self::assertStringContainsString("0 => 'db.password'", $code);
        self::assertStringContainsString("1 => 'db.engine'", $code);
        self::assertStringContainsString('Application::getConnection()', $code);
        self::assertStringContainsString('$connection->getType()', $code);
        self::assertStringNotContainsString('getConnectionType()', $code);
        self::assertStringContainsString(".settings_extra.php", $code);
        self::assertStringContainsString(".settings.php", $code);
        self::assertStringContainsString("php_interface/dbconn.php", $code);
        self::assertStringNotContainsString('DB_HOST', $code);
        self::assertStringNotContainsString('DB_USERNAME', $code);
        self::assertStringNotContainsString('DB_PASSWORD', $code);
        self::assertStringNotContainsString('DB_NAME', $code);
    }

    /** @runInSeparateProcess */
    public function testReadsValuesUsingDocumentedSourcePriority(): void
    {
        $documentRoot = sys_get_temp_dir() . '/bx-cli-remote-config-' . bin2hex(random_bytes(6));
        mkdir($documentRoot . '/bitrix/php_interface', 0775, true);
        file_put_contents(
            $documentRoot . '/bitrix/.settings_extra.php',
            '<?php return ["connections" => ["value" => ["default" => ["host" => "extra-host"]]]];',
        );
        file_put_contents(
            $documentRoot . '/bitrix/.settings.php',
            '<?php return ["connections" => ["value" => ["default" => '
                . '["host" => "settings-host", "login" => "settings-user", "password" => "settings-pass"]]]];',
        );
        file_put_contents(
            $documentRoot . '/bitrix/php_interface/dbconn.php',
            '<?php $DBHost = "dbconn-host"; $DBLogin = "dbconn-user"; $DBPassword = "dbconn-pass"; '
                . '$DBName = "dbconn-database";',
        );
        $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
        $code = (new RemoteConfigPhpCodeBuilder())->build([
            'db.host',
            'db.username',
            'db.password',
            'db.database',
        ]);

        ob_start();
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- выполняем сгенерированный remote-сниппет в тесте.
        eval($code);
        $output = ob_get_clean();
        $result = json_decode(is_string($output) ? $output : '', true);

        self::assertIsArray($result);
        self::assertSame(
            ['extra-host', 'settings-user', 'settings-pass', 'dbconn-database'],
            $result['result'] ?? null,
        );

        unlink($documentRoot . '/bitrix/php_interface/dbconn.php');
        unlink($documentRoot . '/bitrix/.settings.php');
        unlink($documentRoot . '/bitrix/.settings_extra.php');
        rmdir($documentRoot . '/bitrix/php_interface');
        rmdir($documentRoot . '/bitrix');
        rmdir($documentRoot);
    }

    /** @runInSeparateProcess */
    public function testReadsEngineFromBitrixConnectionWithoutNormalization(): void
    {
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- определяем тестовые классы Bitrix в нужном namespace.
        eval(<<<'PHP'
            namespace Bitrix\Main;
            final class TestConnection
            {
                public function getType(): string
                {
                    return 'oracle';
                }
            }
            final class Application
            {
                public static function getConnection(): TestConnection
                {
                    return new TestConnection();
                }
            }
            PHP);
        $_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir() . '/missing-bitrix-root';
        $code = (new RemoteConfigPhpCodeBuilder())->build(['db.engine']);

        ob_start();
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- выполняем сгенерированный remote-сниппет в тесте.
        eval($code);
        $output = ob_get_clean();
        $result = json_decode(is_string($output) ? $output : '', true);

        self::assertIsArray($result);
        self::assertSame(['oracle'], $result['result'] ?? null);
    }

    /** @runInSeparateProcess */
    public function testReadsOnlyCanonicalLegacyConstants(): void
    {
        // phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase
        define('DBHost', 'canonical-host');
        define('DBLogin', 'canonical-user');
        define('DBPassword', 'canonical-pass');
        define('DBName', 'canonical-database');
        // phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase
        define('DB_HOST', 'noncanonical-host');
        $_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir() . '/missing-bitrix-root';
        $code = (new RemoteConfigPhpCodeBuilder())->build([
            'db.host',
            'db.username',
            'db.password',
            'db.database',
        ]);

        ob_start();
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- выполняем сгенерированный remote-сниппет в тесте.
        eval($code);
        $output = ob_get_clean();
        $result = json_decode(is_string($output) ? $output : '', true);

        self::assertIsArray($result);
        self::assertSame(
            ['canonical-host', 'canonical-user', 'canonical-pass', 'canonical-database'],
            $result['result'] ?? null,
        );
    }
}
