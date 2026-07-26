<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Db;

use ModernBx\Cli\App\Console\Command\Bx\Db\DumpCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DumpCommandTest extends TestCase
{
    public function testDefinesCompressOption(): void
    {
        $reflection = new ReflectionClass(DumpCommand::class);
        $command = $reflection->newInstanceWithoutConstructor();
        $parent = $reflection->getParentClass();
        self::assertInstanceOf(ReflectionClass::class, $parent);
        $grandparent = $parent->getParentClass();
        self::assertInstanceOf(ReflectionClass::class, $grandparent);
        $grandparent->getMethod('__construct')->invoke($command);
        $method = $reflection->getMethod('configure');
        $method->invoke($command);

        self::assertTrue($command->getDefinition()->hasOption('compress'));
    }

    public function testCompressesDumpAndDeletesSqlFile(): void
    {
        $directory = $this->createDirectory();
        $dump = $directory . '/dump.sql';
        file_put_contents($dump, 'SELECT 1;');

        try {
            $archive = $this->invoke('compressDump', [$dump]);

            self::assertSame($directory . '/dump.zip', $archive);
            self::assertFileDoesNotExist($dump);
            self::assertFileExists($archive);
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($archive));
            self::assertSame('SELECT 1;', $zip->getFromName('dump.sql'));
            $zip->close();
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testRejectsCompressionWithoutOutputFile(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('dump file must be specified');

        $this->invoke('validateCompression', ['zip', null]);
    }

    /** @param mixed[] $arguments */
    private function invoke(string $methodName, array $arguments): mixed
    {
        $reflection = new ReflectionClass(DumpCommand::class);
        $command = $reflection->newInstanceWithoutConstructor();

        return $reflection->getMethod($methodName)->invokeArgs($command, $arguments);
    }

    private function createDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/db-dump-test-' . bin2hex(random_bytes(6));
        mkdir($directory);

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
}
