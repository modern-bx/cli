<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Vendor;

use ModernBx\Cli\App\Console\Command\Vendor\InstallCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class InstallCommandTest extends TestCase
{
    public function testAuthSnippetIsInsertedAfterAdminerNamespace(): void
    {
        $adminer = <<<'PHP'
            <?php
            /** Adminer */namespace
            Adminer;const VERSION="5.5.1";
            PHP;

        $result = $this->injectAuthSnippet($adminer, '$authenticated = true;');
        $namespaceOffset = strpos($result, 'namespace');
        $authOffset = strpos($result, '$authenticated');
        $versionOffset = strpos($result, 'const VERSION');

        self::assertIsInt($namespaceOffset);
        self::assertIsInt($authOffset);
        self::assertIsInt($versionOffset);
        self::assertLessThan($authOffset, $namespaceOffset);
        self::assertLessThan($versionOffset, $authOffset);
    }

    public function testAuthSnippetIsInsertedAfterOpenTagWhenAdminerHasNoNamespace(): void
    {
        $adminer = '<?php const VERSION="4.8.1";';
        $result = $this->injectAuthSnippet($adminer, '$authenticated = true;');

        self::assertStringStartsWith("<?php \n\$authenticated = true;\n", $result);
    }

    private function injectAuthSnippet(string $adminer, string $snippet): string
    {
        $class = new ReflectionClass(InstallCommand::class);
        $command = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod('injectAuthSnippet');
        $result = $method->invoke($command, $adminer, $snippet);

        self::assertIsString($result);
        return $result;
    }
}
