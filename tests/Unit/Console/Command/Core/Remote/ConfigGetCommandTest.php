<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\Core\Remote\ConfigGetCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ConfigGetCommandTest extends TestCase
{
    public function testParsesSeveralParametersInOriginalOrder(): void
    {
        self::assertSame(
            ['db.password', 'db.engine', 'db.host'],
            $this->parseParameters('db.password, db.engine,db.host'),
        );
    }

    public function testRejectsUnknownParameter(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Неизвестный параметр: db.port');

        $this->parseParameters('db.host,db.port');
    }

    /** @return string[] */
    private function parseParameters(string $parameters): array
    {
        $class = new ReflectionClass(ConfigGetCommand::class);
        $command = $class->newInstanceWithoutConstructor();
        $result = $class->getMethod('parseParameters')->invoke($command, $parameters);

        self::assertIsArray($result);
        return $result;
    }
}
