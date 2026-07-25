<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Adminer;

use ModernBx\Cli\App\Console\Command\Adminer\ImportCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;

final class ImportCommandTest extends TestCase
{
    public function testGzipDumpGetsExpectedRemoteFilename(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'adminer-import-');
        self::assertIsString($source);
        $gzipSource = $source . '.gz';
        rename($source, $gzipSource);

        try {
            $options = [
                'remote' => 'production',
                'password' => 'basic-secret',
                'db-engine' => 'mysql',
                'database' => 'project',
                'db-host' => 'mysql',
                'db-user' => 'project',
                'db-password' => 'db-secret',
                'path' => '/',
                'format' => 'table',
            ];
            $input = $this->createMock(InputInterface::class);
            $input->method('getArgument')->with('src')->willReturn($gzipSource);
            $input->method('getOption')->willReturnCallback(
                static fn (string $name): mixed => $options[$name] ?? null,
            );

            $result = $this->invoke('validateInput', [$input]);

            self::assertIsArray($result);
            self::assertSame('adminer.sql.gz', $result['dump_filename'] ?? null);
            self::assertSame('/', $result['path'] ?? null);
        } finally {
            @unlink($gzipSource);
        }
    }

    public function testNormalizesAdminerDirectory(): void
    {
        self::assertSame('/tools/adminer', $this->invoke('normalizePath', ['tools/adminer/']));
    }

    /** @param mixed[] $arguments */
    private function invoke(string $methodName, array $arguments): mixed
    {
        $reflection = new ReflectionClass(ImportCommand::class);
        $command = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($command, $arguments);
    }
}
