<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Service\Remote;

use ModernBx\Cli\App\Service\Remote\RemoteUserPhpCodeBuilder;
use PHPUnit\Framework\TestCase;

final class RemoteUserPhpCodeBuilderTest extends TestCase
{
    public function testBuildPasswordSetSafelyEmbedsArguments(): void
    {
        $code = (new RemoteUserPhpCodeBuilder())->buildPasswordSet('EMAIL', "user'o@example.com", "pa'ss");

        self::assertStringContainsString("\$field = 'EMAIL';", $code);
        self::assertStringContainsString("\$value = 'user\\'o@example.com';", $code);
        self::assertStringContainsString("\$password = 'pa\\'ss';", $code);
        self::assertStringContainsString('CUser::GetList', $code);
        self::assertStringContainsString("'CONFIRM_PASSWORD' => \$password", $code);
    }
}
