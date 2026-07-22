<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Console\Command\Core\Remote\RegisterCommand;
use ModernBx\Cli\App\Service\Remote\ProjectNameGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

final class RegisterCommandTest extends TestCase
{
    private string $home;
    private string|false $previousHome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->home = sys_get_temp_dir() . '/bx-cli-register-test-' . bin2hex(random_bytes(6));
        mkdir($this->home, 0700, true);
        $this->previousHome = getenv('HOME');
        putenv('HOME=' . $this->home);
        $_SERVER['HOME'] = $this->home;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->home);

        if ($this->previousHome === false) {
            putenv('HOME');
            unset($_SERVER['HOME']);
        } else {
            putenv('HOME=' . $this->previousHome);
            $_SERVER['HOME'] = $this->previousHome;
        }

        parent::tearDown();
    }

    public function testRemoteRegisterUsesEndpointHostAsDefaultCodename(): void
    {
        $command = new class(new ProjectNameGenerator()) extends RegisterCommand {
            protected function login(string $endpoint, string $login, string $password): array
            {
                return ['value' => 'session-id', 'expires' => '2026-07-22T00:00:00+00:00'];
            }
        };
        $command->setName('remote:register');
        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['admin', 'secret']);

        $exitCode = $tester->execute(['endpoint' => 'https://Example.COM:8443']);

        self::assertSame(AppCommand::CODE_SUCCESS, $exitCode, $tester->getDisplay());
        self::assertFileExists($this->home . '/.config/bx-cli/projects/example.com/project.yaml');

        $config = Yaml::parseFile($this->home . '/.config/bx-cli/projects/example.com/project.yaml');
        self::assertIsArray($config);
        self::assertSame('example.com', $config['data']['project']['name'] ?? null);
        self::assertSame('https://example.com:8443', $config['data']['project']['endpoint'] ?? null);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
