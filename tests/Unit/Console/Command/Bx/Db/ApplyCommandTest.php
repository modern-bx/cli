<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Db;

use ModernBx\Cli\App\Console\Command\Bx\Db\ApplyCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Output\BufferedOutput;

final class ApplyCommandTest extends TestCase
{
    public function testDefinesOutputOptionsWithTableDefault(): void
    {
        $reflection = new ReflectionClass(ApplyCommand::class);
        $command = $reflection->newInstanceWithoutConstructor();
        $parent = $reflection->getParentClass();
        self::assertInstanceOf(ReflectionClass::class, $parent);
        $grandparent = $parent->getParentClass();
        self::assertInstanceOf(ReflectionClass::class, $grandparent);
        $grandparent->getMethod('__construct')->invoke($command);
        $reflection->getMethod('configure')->invoke($command);

        self::assertSame('table', $command->getDefinition()->getOption('format')->getDefault());
        self::assertTrue($command->getDefinition()->hasOption('void'));
    }

    public function testRendersJsonAndCsvResults(): void
    {
        $results = [['columns' => ['id', 'name'], 'rows' => [['1', 'test']]]];
        $jsonOutput = new BufferedOutput();
        $csvOutput = new BufferedOutput();

        $this->invoke('renderResults', [$jsonOutput, 'json', $results]);
        $this->invoke('renderResults', [$csvOutput, 'csv', $results]);

        self::assertStringContainsString('"columns"', $jsonOutput->fetch());
        self::assertSame("id,name\n1,test\n", $csvOutput->fetch());
    }

    public function testRejectsUnknownOutputFormat(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('getOutputFormat', ['xml']);
    }

    public function testReadsSqlFilesFromDirectoryAndZipInNameOrder(): void
    {
        $directory = $this->createDirectory();
        file_put_contents($directory . '/01.sql', 'directory-first;');
        file_put_contents($directory . '/ignored.txt', 'ignored;');
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($directory . '/02.zip', \ZipArchive::CREATE));
        $archive->addFromString('20.sql', 'archive-second;');
        $archive->addFromString('10.sql', 'archive-first;');
        $archive->addFromString('nested/00.sql', 'nested-ignored;');
        $archive->addFromString('not-sql.txt', 'ignored;');
        $archive->close();

        try {
            $scripts = $this->invoke('readSqlScripts', [$directory]);
            self::assertIsArray($scripts);
            self::assertSame(['01.sql', '02.zip/10.sql', '02.zip/20.sql'], array_column($scripts, 'name'));
            self::assertSame(
                "directory-first;\narchive-first;\narchive-second;",
                $this->invoke('readSql', [$directory]),
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testGlobFindsAndSortsSqlAndZipFiles(): void
    {
        $directory = $this->createDirectory();
        file_put_contents($directory . '/20.sql', 'last;');
        file_put_contents($directory . '/10.sql', 'first;');

        try {
            self::assertSame("first;\nlast;", $this->invoke('readSql', [$directory . '/*.sql']));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    /** @param mixed[] $arguments */
    private function invoke(string $methodName, array $arguments): mixed
    {
        $reflection = new ReflectionClass(ApplyCommand::class);
        $command = $reflection->newInstanceWithoutConstructor();

        return $reflection->getMethod($methodName)->invokeArgs($command, $arguments);
    }

    private function createDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/db-apply-test-' . bin2hex(random_bytes(6));
        mkdir($directory);

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($directory);
    }
}
