<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\User;

use ModernBx\Cli\App\Console\Command\Bx\User\PasswordSetCommand;
use PHPUnit\Framework\TestCase;

final class PasswordSetCommandTest extends TestCase
{
    public function testLocalImplementationDoesNotContainScopableCUserReference(): void
    {
        $reflection = new \ReflectionClass(PasswordSetCommand::class);
        $source = file_get_contents((string) $reflection->getFileName());

        self::assertIsString($source);
        self::assertStringNotContainsString('\\CUser', $source);
        self::assertStringContainsString("\$userClass = implode('', ['C', 'User']);", $source);
    }
}
