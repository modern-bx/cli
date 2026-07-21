<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Console\Command\Core\Remote\ListCommand;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ListCommandTest extends TestCase
{
    private string|false $previousRemote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousRemote = getenv(AppCommand::ENV_REMOTE);
        putenv(AppCommand::ENV_REMOTE . '=prod');
        $_SERVER[AppCommand::ENV_REMOTE] = 'prod';
    }

    protected function tearDown(): void
    {
        if ($this->previousRemote === false) {
            putenv(AppCommand::ENV_REMOTE);
            unset($_SERVER[AppCommand::ENV_REMOTE]);
        } else {
            putenv(AppCommand::ENV_REMOTE . '=' . $this->previousRemote);
            $_SERVER[AppCommand::ENV_REMOTE] = $this->previousRemote;
        }

        parent::tearDown();
    }

    public function testRemoteListIgnoresSessionRemoteContext(): void
    {
        $command = new ListCommand(new ProjectRegistry());
        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(AppCommand::CODE_SUCCESS, $exitCode);
        self::assertStringNotContainsString('не поддерживает remote', $tester->getDisplay());
    }
}
