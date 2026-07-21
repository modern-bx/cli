<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\Bx\Backup\ListCommand;
use ModernBx\Url\UrlImmutable;
use PHPUnit\Framework\TestCase;

final class ListCommandTest extends TestCase
{
    private ?string $tempDirectory = null;

    protected function tearDown(): void
    {
        if ($this->tempDirectory !== null) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testListsOnlyMainGzBackupFiles(): void
    {
        $documentRoot = $this->createDocumentRoot();
        $backupDirectory = $documentRoot . '/bitrix/backup';
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz', 'main');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz.1', 'volume 1');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz.2', 'volume 2');
        $this->writeFile($backupDirectory . '/2026-07-21.tar.gz', 'second');
        $this->writeFile($backupDirectory . '/ignored.sql', 'sql');

        $items = $this->runList($documentRoot);

        self::assertCount(2, $items);
        self::assertSame('2026-07-20.tar.gz', $items[0]['name']);
        self::assertSame('/bitrix/backup/2026-07-20.tar.gz', $items[0]['path']);
        self::assertSame([1, 2], $items[0]['volumes']);
        self::assertSame('2026-07-21.tar.gz', $items[1]['name']);
        self::assertSame([], $items[1]['volumes']);
    }

    public function testFailsWhenVolumeNumbersHaveGap(): void
    {
        $documentRoot = $this->createDocumentRoot();
        $backupDirectory = $documentRoot . '/bitrix/backup';
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz', 'main');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz.1', 'volume 1');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz.3', 'volume 3');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ожидается .2, найден .3');

        $this->runList($documentRoot);
    }

    /** @return list<array<string, mixed>> */
    private function runList(string $documentRoot): array
    {
        $command = new ListCommand();
        $property = new \ReflectionProperty(ListCommand::class, 'documentRoot');
        $property->setAccessible(true);
        $property->setValue($command, UrlImmutable::create($documentRoot));
        $method = new \ReflectionMethod(ListCommand::class, 'executeLocal');
        $method->setAccessible(true);
        $result = $method->invoke($command);

        self::assertIsArray($result);

        return $result;
    }

    private function createDocumentRoot(): string
    {
        $directory = sys_get_temp_dir() . '/framework-cli-backup-list-' . bin2hex(random_bytes(8));
        $backupDirectory = $directory . '/bitrix/backup';

        if (!mkdir($backupDirectory, 0777, true) && !is_dir($backupDirectory)) {
            throw new \RuntimeException('Unable to create temporary backup directory.');
        }

        $this->tempDirectory = $directory;

        return $directory;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write temporary file.');
        }
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
