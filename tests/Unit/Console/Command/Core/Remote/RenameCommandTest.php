<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Console\Command\Core\Remote\RenameCommand;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

final class RenameCommandTest extends TestCase
{
    private string $home;
    private string|false $previousHome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->home = sys_get_temp_dir() . '/bx-cli-rename-test-' . bin2hex(random_bytes(6));
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

    public function testRemoteRenameMovesProjectConfigDirectory(): void
    {
        $this->createProject('prod');
        $command = new RenameCommand(new ProjectRegistry());
        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['prev' => 'prod', 'next' => 'stage']);

        self::assertSame(AppCommand::CODE_SUCCESS, $exitCode, $tester->getDisplay());
        self::assertFileDoesNotExist($this->projectFile('prod'));
        self::assertFileExists($this->projectFile('stage'));

        $config = Yaml::parseFile($this->projectFile('stage'));
        self::assertIsArray($config);
        self::assertSame('stage', $config['data']['project']['name'] ?? null);
    }

    public function testRemoteRenameFailsWhenPreviousProjectDoesNotExist(): void
    {
        $command = new RenameCommand(new ProjectRegistry());
        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['prev' => 'missing', 'next' => 'stage']);

        self::assertSame(AppCommand::CODE_IO_ERROR, $exitCode);
        self::assertStringContainsString('Проект не зарегистрирован: missing', $tester->getDisplay());
    }

    public function testRemoteRenameFailsWhenNextProjectExists(): void
    {
        $this->createProject('prod');
        $this->createProject('stage');
        $command = new RenameCommand(new ProjectRegistry());
        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['prev' => 'prod', 'next' => 'stage']);

        self::assertSame(AppCommand::CODE_IO_ERROR, $exitCode);
        self::assertStringContainsString('Кодовое имя проекта уже занято: stage', $tester->getDisplay());
    }

    private function createProject(string $codename): void
    {
        $file = $this->projectFile($codename);
        mkdir(dirname($file), 0700, true);
        file_put_contents($file, Yaml::dump(['data' => ['project' => ['name' => $codename]]], 4, 2));
    }

    private function projectFile(string $codename): string
    {
        return $this->home . '/.config/bx-cli/projects/' . $codename . '/project.yaml';
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
