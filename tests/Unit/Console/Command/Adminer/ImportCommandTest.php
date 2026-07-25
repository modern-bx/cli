<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Adminer;

use ModernBx\Cli\App\Console\Command\Adminer\ImportCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Vendor\AdminerClient;
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

    public function testDatabaseOptionsUseSavedValuesAndCommandOverrides(): void
    {
        $saved = [
            'db.engine' => 'mysql',
            'db.host' => 'saved-host',
            'db.username' => 'saved-user',
            'db.password' => 'saved-password',
            'db.database' => 'saved-database',
        ];
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnCallback(
            static fn (string $name): mixed => $name === 'db.host' ? 'override-host' : null,
        );

        $result = $this->invoke('resolveDatabaseOptions', [$input, $saved]);

        self::assertIsArray($result);
        self::assertSame('override-host', $result['db.host'] ?? null);
        self::assertSame('saved-user', $result['db.username'] ?? null);
    }

    public function testDatabaseOptionsReportMissingParameter(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Не хватает параметра --db.engine');

        $this->invoke('resolveDatabaseOptions', [$input, []]);
    }

    public function testDefinesCanonicalDatabaseAndDumpLifecycleOptions(): void
    {
        $bitrixClient = new BitrixAdminClient();
        $command = new ImportCommand(
            new RemoteProjectConfigManager(new ProjectRegistry(), $bitrixClient),
            $bitrixClient,
            new AdminerClient(),
        );

        foreach (['db.engine', 'db.host', 'db.username', 'db.password', 'db.database', 'force', 'no-delete'] as $name) {
            self::assertTrue($command->getDefinition()->hasOption($name));
        }
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
