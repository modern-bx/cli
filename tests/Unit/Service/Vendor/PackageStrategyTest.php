<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Service\Vendor;

use ModernBx\Cli\App\Service\Vendor\AdminerPackageStrategy;
use ModernBx\Cli\App\Service\Vendor\InstallationPath;
use ModernBx\Cli\App\Service\Vendor\PackageStrategy;
use PHPUnit\Framework\TestCase;

final class PackageStrategyTest extends TestCase
{
    public function testAdminerSupportsRootAsDefaultPath(): void
    {
        $strategy = new AdminerPackageStrategy();

        self::assertSame('', InstallationPath::resolveForInstall(null, $strategy));
    }

    public function testInstallationPathIsNormalizedAsDirectory(): void
    {
        $strategy = new AdminerPackageStrategy();

        self::assertSame('/tools/database', InstallationPath::resolveForInstall('/tools//database/', $strategy));
    }

    public function testExplicitPathIsRequiredWhenPackageHasNoDefault(): void
    {
        $strategy = $this->createMock(PackageStrategy::class);
        $strategy->method('supportsDefaultPath')->willReturn(false);
        $strategy->method('getName')->willReturn('example');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('необходимо указать опцию --path');

        InstallationPath::resolveForInstall(null, $strategy);
    }

    public function testUninstallUsesRootByDefault(): void
    {
        self::assertSame('', InstallationPath::resolveForUninstall(null));
    }

    public function testInstallationPathCannotLeaveDocumentRoot(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('не должен выходить за document root');

        InstallationPath::resolveForInstall('../adminer', new AdminerPackageStrategy());
    }

    public function testAdminerPresenceIsDeterminedByItsMainFile(): void
    {
        $documentRoot = sys_get_temp_dir() . '/bx-cli-vendor-strategy-' . bin2hex(random_bytes(6));
        mkdir($documentRoot . '/tools', 0775, true);
        $strategy = new AdminerPackageStrategy();

        self::assertFalse($strategy->isInstalledLocally($documentRoot, '/tools'));
        touch($documentRoot . '/tools/adminer.php');
        self::assertTrue($strategy->isInstalledLocally($documentRoot, '/tools'));

        unlink($documentRoot . '/tools/adminer.php');
        rmdir($documentRoot . '/tools');
        rmdir($documentRoot);
    }
}
