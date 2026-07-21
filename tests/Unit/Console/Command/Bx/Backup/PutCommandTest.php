<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\Bx\Backup\PutCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteBackupPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Url\UrlImmutable;
use ModernBx\Cli\Common\Console\Printer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class PutCommandTest extends TestCase
{
    private ?string $tempDirectory = null;

    protected function tearDown(): void
    {
        if ($this->tempDirectory !== null) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testFindsMainBackupAndOrderedVolumes(): void
    {
        $directory = $this->createTempDirectory();
        $mainPath = $directory . '/2026-07-20.tar.gz';
        $this->writeFile($mainPath, 'main');
        $this->writeFile($mainPath . '.1', 'one');
        $this->writeFile($mainPath . '.2', 'two');

        self::assertSame(
            [$mainPath, $mainPath . '.1', $mainPath . '.2'],
            $this->findBackupVolumePaths($mainPath, false),
        );
    }

    public function testMissingVolumeFailsWithoutForce(): void
    {
        $directory = $this->createTempDirectory();
        $mainPath = $directory . '/2026-07-20.tar.gz';
        $this->writeFile($mainPath, 'main');
        $this->writeFile($mainPath . '.1', 'one');
        $this->writeFile($mainPath . '.3', 'three');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('В резервной копии пропущен том с номером 2.');

        $this->findBackupVolumePaths($mainPath, false);
    }

    public function testForceAllowsMissingVolume(): void
    {
        $directory = $this->createTempDirectory();
        $mainPath = $directory . '/2026-07-20.tar.gz';
        $this->writeFile($mainPath, 'main');
        $this->writeFile($mainPath . '.1', 'one');
        $this->writeFile($mainPath . '.3', 'three');

        self::assertSame(
            [$mainPath, $mainPath . '.1', $mainPath . '.3'],
            $this->findBackupVolumePaths($mainPath, true),
        );
    }

    public function testLocalPutCopiesVolumes(): void
    {
        $directory = $this->createTempDirectory();
        $sourceMain = $directory . '/source/2026-07-20.tar.gz';
        $documentRoot = $directory . '/www';
        $backupDirectory = $documentRoot . '/bitrix/backup';
        $this->makeDirectory(dirname($sourceMain));
        $this->makeDirectory($backupDirectory);
        $this->writeFile($sourceMain, 'main');
        $this->writeFile($sourceMain . '.1', 'one');

        $command = $this->createCommand();
        $property = new \ReflectionProperty(PutCommand::class, 'documentRoot');
        $property->setAccessible(true);
        $property->setValue($command, UrlImmutable::create($documentRoot));
        $output = new BufferedOutput();
        $printer = new Printer($output);
        $printerProperty = new \ReflectionProperty(PutCommand::class, 'printer');
        $printerProperty->setAccessible(true);
        $printerProperty->setValue($command, $printer);

        $method = new \ReflectionMethod(PutCommand::class, 'executeLocal');
        $method->setAccessible(true);
        $method->invoke($command, [$sourceMain, $sourceMain . '.1'], false);

        self::assertSame('main', file_get_contents($backupDirectory . '/2026-07-20.tar.gz'));
        self::assertSame('one', file_get_contents($backupDirectory . '/2026-07-20.tar.gz.1'));
        self::assertStringContainsString(
            'Загружен том 2026-07-20.tar.gz: 4 Б, всего 4 Б.',
            $output->fetch(),
        );
    }

    public function testLocalPutFailsOnConflictWithoutForce(): void
    {
        $directory = $this->createTempDirectory();
        $sourceMain = $directory . '/source/2026-07-20.tar.gz';
        $documentRoot = $directory . '/www';
        $backupDirectory = $documentRoot . '/bitrix/backup';
        $this->makeDirectory(dirname($sourceMain));
        $this->makeDirectory($backupDirectory);
        $this->writeFile($sourceMain, 'main');
        $this->writeFile($backupDirectory . '/2026-07-20.tar.gz', 'exists');

        $command = $this->createCommand();
        $property = new \ReflectionProperty(PutCommand::class, 'documentRoot');
        $property->setAccessible(true);
        $property->setValue($command, UrlImmutable::create($documentRoot));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Файл уже существует:');

        $method = new \ReflectionMethod(PutCommand::class, 'executeLocal');
        $method->setAccessible(true);
        $method->invoke($command, [$sourceMain], false);
    }

    /** @return list<string> */
    private function findBackupVolumePaths(string $mainPath, bool $force): array
    {
        $command = $this->createCommand();
        $method = new \ReflectionMethod(PutCommand::class, 'findBackupVolumePaths');
        $method->setAccessible(true);
        $result = $method->invoke($command, $mainPath, $force);

        self::assertIsArray($result);

        return $result;
    }

    private function createCommand(): PutCommand
    {
        $reflection = new \ReflectionClass(RemoteProjectConfigManager::class);
        $configManager = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(BitrixAdminClient::class);
        $bitrixAdminClient = $reflection->newInstanceWithoutConstructor();

        return new PutCommand($configManager, $bitrixAdminClient, new RemoteBackupPhpCodeBuilder());
    }

    private function createTempDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/framework-cli-backup-put-' . bin2hex(random_bytes(8));
        $this->makeDirectory($directory);
        $this->tempDirectory = $directory;

        return $directory;
    }

    private function makeDirectory(string $directory): void
    {
        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create temporary directory.');
        }
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
