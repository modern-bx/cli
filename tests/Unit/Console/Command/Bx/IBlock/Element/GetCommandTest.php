<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\IBlock\Element;

use ModernBx\Cli\App\Console\Command\Bx\IBlock\Element\GetCommand;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteIBlockElementPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

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

    public function testRemoteModeReceivesNormalizedFieldsArrayAndFormatsLocally(): void
    {
        $command = $this->createCommand();
        $command->remoteResponses[] = json_encode([
            'ok' => true,
            'result' => [
                'ID' => '17',
                'WF_COMMENTS' => null,
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $input = new ArrayInput([
            'ID' => '17',
            '--pretty' => true,
        ], $command->getDefinition());
        $input->setInteractive(false);

        $output = $command->runRemote($input, 'prod');

        self::assertSame(json_encode([
            'ID' => '17',
            'WF_COMMENTS' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL, $output);
        self::assertCount(1, $command->remoteExecutions);
        self::assertStringContainsString('CIBlockElement::GetList', $command->remoteExecutions[0]['code']);
        self::assertStringNotContainsString('$jsonFlags', $command->remoteExecutions[0]['code']);
    }

    private function createCommand(): TestableIBlockElementGetCommand
    {
        $reflection = new \ReflectionClass(ClassAliasLoader::class);
        $aliasLoader = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(RemoteProjectConfigManager::class);
        $configManager = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(BitrixAdminClient::class);
        $client = $reflection->newInstanceWithoutConstructor();

        return new TestableIBlockElementGetCommand(
            $aliasLoader,
            $configManager,
            $client,
            new RemoteIBlockElementPhpCodeBuilder(),
        );
    }
}
