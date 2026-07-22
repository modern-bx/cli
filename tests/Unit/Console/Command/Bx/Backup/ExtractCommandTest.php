<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Console\Command\Bx\Backup\ExtractCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ExtractCommandTest extends TestCase
{
    private string|false $previousRemote;
    private ?string $tempDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousRemote = getenv(AppCommand::ENV_REMOTE);
        putenv(AppCommand::ENV_REMOTE . '=citadel');
        $_SERVER[AppCommand::ENV_REMOTE] = 'citadel';
    }

    protected function tearDown(): void
    {
        if ($this->tempDirectory !== null && is_dir($this->tempDirectory)) {
            rmdir($this->tempDirectory);
        }

        if ($this->previousRemote === false) {
            putenv(AppCommand::ENV_REMOTE);
            unset($_SERVER[AppCommand::ENV_REMOTE]);
        } else {
            putenv(AppCommand::ENV_REMOTE . '=' . $this->previousRemote);
            $_SERVER[AppCommand::ENV_REMOTE] = $this->previousRemote;
        }

        parent::tearDown();
    }

    public function testIgnoresSessionRemoteContext(): void
    {
        $command = new ExtractCommand();
        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($command);
        $this->tempDirectory = sys_get_temp_dir() . '/bx-cli-backup-extract-test-' . bin2hex(random_bytes(6));

        $tester->execute([
            'archive' => $this->tempDirectory . '/missing.tar.gz',
            'destination' => $this->tempDirectory,
        ]);

        self::assertStringNotContainsString('не поддерживает remote', $tester->getDisplay());
        self::assertStringContainsString('Archive volume does not exist', $tester->getDisplay());
    }
}
