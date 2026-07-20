<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\IBlock\Element;

use ModernBx\Cli\App\Console\Command\Bx\IBlock\Element\GetCommand;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteIBlockElementPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use PHPUnit\Framework\TestCase;

final class GetCommandTest extends TestCase
{
    public function testNormalizeTildeFieldsOverwritesPlainKeysAndRemovesTildeKeys(): void
    {
        $method = new \ReflectionMethod(GetCommand::class, 'normalizeTildeFields');
        $method->setAccessible(true);

        $fields = [
            'ID' => '17',
            'WF_COMMENTS' => 'escaped comment',
            '~WF_COMMENTS' => null,
            '~NAME' => 'Raw name',
        ];

        self::assertSame([
            'ID' => '17',
            'WF_COMMENTS' => null,
            'NAME' => 'Raw name',
        ], $method->invoke($this->createCommand(), $fields));
    }

    private function createCommand(): GetCommand
    {
        $reflection = new \ReflectionClass(ClassAliasLoader::class);
        $aliasLoader = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(RemoteProjectConfigManager::class);
        $configManager = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(BitrixAdminClient::class);
        $client = $reflection->newInstanceWithoutConstructor();

        return new GetCommand(
            $aliasLoader,
            $configManager,
            $client,
            new RemoteIBlockElementPhpCodeBuilder(),
        );
    }
}
