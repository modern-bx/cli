<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\Bx\Backup\GetCommand;
use ModernBx\Cli\Common\Console\Printer;
use ModernBx\Url\UrlImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class GetCommandTest extends TestCase
{
    private ?string $tempDirectory = null;

    protected function tearDown(): void
    {
        if ($this->tempDirectory !== null) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testDownloadsMainBackupAndNumberedVolumes(): void
    {
        $documentRoot = $this->createDocumentRoot();
        $backupDirectory = $documentRoot . '/bitrix/backup';
        $destinationDirectory = $this->createDirectory('download');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz', str_repeat('a', 10));
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz.2', str_repeat('b', 2048));
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz.1', str_repeat('c', 1024));
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz.tmp', 'ignored');

        $output = $this->runGet($documentRoot, '2026-07-20.tar.gz', $destinationDirectory, false);

        self::assertFileEquals(
            $backupDirectory . '/2026-07-20.tar.gz',
            $destinationDirectory . '/2026-07-20.tar.gz',
        );
        self::assertFileEquals(
            $backupDirectory . '/2026-07-20.tar.gz.1',
            $destinationDirectory . '/2026-07-20.tar.gz.1',
        );
        self::assertFileEquals(
            $backupDirectory . '/2026-07-20.tar.gz.2',
            $destinationDirectory . '/2026-07-20.tar.gz.2',
        );
        self::assertStringContainsString('Скачан том 2026-07-20.tar.gz: 10 Б, всего 10 Б.', $output);
        self::assertStringContainsString('Скачан том 2026-07-20.tar.gz.1: 1.00 КБ, всего 1.01 КБ.', $output);
        self::assertStringContainsString('Скачан том 2026-07-20.tar.gz.2: 2.00 КБ, всего 3.01 КБ.', $output);
    }

    public function testFailsWhenDestinationVolumeExistsWithoutForce(): void
    {
        $documentRoot = $this->createDocumentRoot();
        $backupDirectory = $documentRoot . '/bitrix/backup';
        $destinationDirectory = $this->createDirectory('download');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz', 'source');
        $this->writeFile($destinationDirectory . '/2026-07-20.tar.gz', 'existing');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Файл уже существует');

        $this->runGet($documentRoot, '2026-07-20.tar.gz', $destinationDirectory, false);
    }

    public function testForceOverwritesExistingDestinationVolume(): void
    {
        $documentRoot = $this->createDocumentRoot();
        $backupDirectory = $documentRoot . '/bitrix/backup';
        $destinationDirectory = $this->createDirectory('download');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz', 'source');
        $this->writeFile($destinationDirectory . '/2026-07-20.tar.gz', 'existing');

        $this->runGet($documentRoot, '2026-07-20.tar.gz', $destinationDirectory, true);

        self::assertSame('source', (string) file_get_contents($destinationDirectory . '/2026-07-20.tar.gz'));
    }

    public function testRejectsBackupNameWithPath(): void
    {
        $command = new GetCommand();
        $method = new \ReflectionMethod(GetCommand::class, 'normalizeBackupName');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('короткое имя');

        $method->invoke($command, '../2026-07-20.tar.gz');
    }

    private function runGet(string $documentRoot, string $backup, string $destinationDirectory, bool $force): string
    {
        $command = new GetCommand();
        $output = new BufferedOutput();
        $documentRootProperty = new \ReflectionProperty(GetCommand::class, 'documentRoot');
        $documentRootProperty->setAccessible(true);
        $documentRootProperty->setValue($command, UrlImmutable::create($documentRoot));
        $printerProperty = new \ReflectionProperty(GetCommand::class, 'printer');
        $printerProperty->setAccessible(true);
        $printerProperty->setValue($command, new Printer($output));
        $method = new \ReflectionMethod(GetCommand::class, 'executeLocal');
        $method->setAccessible(true);
        $method->invoke($command, $backup, $destinationDirectory, $force);

        return $output->fetch();
    }

    private function createDocumentRoot(): string
    {
        $directory = $this->createDirectory('document-root');
        $backupDirectory = $directory . '/bitrix/backup';

        if (!mkdir($backupDirectory, 0777, true) && !is_dir($backupDirectory)) {
            throw new \RuntimeException('Unable to create temporary backup directory.');
        }

        return $directory;
    }

    private function createDirectory(string $name): string
    {
        if ($this->tempDirectory === null) {
            $this->tempDirectory = sys_get_temp_dir() . '/framework-cli-backup-get-' . bin2hex(random_bytes(8));

            if (!mkdir($this->tempDirectory, 0777, true) && !is_dir($this->tempDirectory)) {
                throw new \RuntimeException('Unable to create temporary directory.');
            }
        }

        $directory = $this->tempDirectory . '/' . $name;

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create temporary directory.');
        }

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
