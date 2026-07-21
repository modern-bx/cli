<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\Bx\Backup\ListCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteBackupPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
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
        self::assertFalse($items[0]['incomplete']);
        self::assertNull($items[0]['missing_volume']);
        self::assertSame('2026-07-21.tar.gz', $items[1]['name']);
        self::assertSame([], $items[1]['volumes']);
    }

    public function testMarksBackupWithMissingVolumeAsIncomplete(): void
    {
        $documentRoot = $this->createDocumentRoot();
        $backupDirectory = $documentRoot . '/bitrix/backup';
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz', 'main');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz.1', 'volume 1');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz.3', 'volume 3');

        $items = $this->runList($documentRoot);

        self::assertCount(1, $items);
        self::assertSame([1, 3], $items[0]['volumes']);
        self::assertTrue($items[0]['incomplete']);
        self::assertSame(2, $items[0]['missing_volume']);
    }

    public function testDefaultFilterSkipsIncompleteBackups(): void
    {
        $items = [
            ['path' => '/bitrix/backup/complete.tar.gz', 'incomplete' => false],
            ['path' => '/bitrix/backup/incomplete.tar.gz', 'incomplete' => true],
        ];

        self::assertSame([$items[0]], $this->filterItems($items, false, false));
    }

    public function testListAllFilterKeepsIncompleteBackups(): void
    {
        $items = [
            ['path' => '/bitrix/backup/complete.tar.gz', 'incomplete' => false],
            ['path' => '/bitrix/backup/incomplete.tar.gz', 'incomplete' => true],
        ];

        self::assertSame($items, $this->filterItems($items, true, false));
    }

    public function testListIncompleteFilterKeepsOnlyIncompleteBackups(): void
    {
        $items = [
            ['path' => '/bitrix/backup/complete.tar.gz', 'incomplete' => false],
            ['path' => '/bitrix/backup/incomplete.tar.gz', 'incomplete' => true],
        ];

        self::assertSame([$items[1]], $this->filterItems($items, false, true));
    }

    public function testFormatsBackupByShortName(): void
    {
        $command = $this->createCommand();
        $method = new \ReflectionMethod(ListCommand::class, 'formatItem');
        $method->setAccessible(true);

        self::assertSame(
            '2026-07-20.tar.gz',
            $method->invoke($command, [
                'name' => '2026-07-20.tar.gz',
                'path' => '/bitrix/backup/2026-07-20.tar.gz',
            ]),
        );
    }

    /** @return list<array<string, mixed>> */
    private function runList(string $documentRoot): array
    {
        $command = $this->createCommand();
        $property = new \ReflectionProperty(ListCommand::class, 'documentRoot');
        $property->setAccessible(true);
        $property->setValue($command, UrlImmutable::create($documentRoot));
        $method = new \ReflectionMethod(ListCommand::class, 'executeLocal');
        $method->setAccessible(true);
        $result = $method->invoke($command);

        self::assertIsArray($result);

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function filterItems(array $items, bool $listAll, bool $listIncomplete): array
    {
        $command = $this->createCommand();
        $method = new \ReflectionMethod(ListCommand::class, 'filterItems');
        $method->setAccessible(true);
        $result = $method->invoke($command, $items, $listAll, $listIncomplete);

        self::assertIsArray($result);

        return $result;
    }


    private function createCommand(): ListCommand
    {
        $reflection = new \ReflectionClass(RemoteProjectConfigManager::class);
        $configManager = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(BitrixAdminClient::class);
        $bitrixAdminClient = $reflection->newInstanceWithoutConstructor();

        return new ListCommand($configManager, $bitrixAdminClient, new RemoteBackupPhpCodeBuilder());
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
