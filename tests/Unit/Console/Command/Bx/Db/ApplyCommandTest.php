<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Db;

use ModernBx\Cli\App\Console\Command\Bx\Db\ApplyCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ApplyCommandTest extends TestCase
{
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
