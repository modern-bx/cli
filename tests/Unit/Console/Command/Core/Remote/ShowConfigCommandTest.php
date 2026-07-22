<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Console\Command\Core\Remote\ShowConfigCommand;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ShowConfigCommandTest extends TestCase
{
    private string|false $previousHome;
    private string $home;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousHome = getenv('HOME');
        $this->home = sys_get_temp_dir() . '/bx-cli-show-config-' . bin2hex(random_bytes(4));
        mkdir($this->home . '/.config/bx-cli/projects/prod', 0700, true);
        file_put_contents(
            $this->home . '/.config/bx-cli/projects/prod/project.yaml',
            "endpoint: https://example.com/bitrix/admin/\nlogin: admin\n",
        );
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

    public function testPrintsRawYamlConfigByDefault(): void
    {
        $command = new ShowConfigCommand(new ProjectRegistry());
        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['codename' => 'prod']);

        self::assertSame(AppCommand::CODE_SUCCESS, $exitCode);
        self::assertSame("endpoint: https://example.com/bitrix/admin/\nlogin: admin\n", $tester->getDisplay());
    }

    public function testPrintsJsonConfig(): void
    {
        $command = new ShowConfigCommand(new ProjectRegistry());
        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['codename' => 'prod', '--format' => 'json']);

        self::assertSame(AppCommand::CODE_SUCCESS, $exitCode);
        self::assertJson($tester->getDisplay());
        self::assertStringContainsString('"endpoint": "https://example.com/bitrix/admin/"', $tester->getDisplay());
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
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

        rmdir($dir);
    }
}
