<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\CFile;

use ModernBx\Cli\App\Console\Command\Bx\CFile\GetCommand;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteFilePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

final class GetCommandTest extends TestCase
{
    public function testNormalizeIdAcceptsPositiveInteger(): void
    {
        $command = $this->createCommand();
        $method = new \ReflectionMethod(GetCommand::class, 'normalizeId');
        $method->setAccessible(true);

        self::assertSame(123, $method->invoke($command, ' 123 '));
    }

    /** @dataProvider invalidIdProvider */
    public function testNormalizeIdRejectsInvalidValue(string $value): void
    {
        $command = $this->createCommand();
        $method = new \ReflectionMethod(GetCommand::class, 'normalizeId');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $method->invoke($command, $value);
    }

    /** @return iterable<string, array{string}> */
    public function invalidIdProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'float' => ['1.5'];
        yield 'string' => ['abc'];
    }

    public function testRemoteModeBuildsPhpSnippetAndPrintsResponse(): void
    {
        $command = $this->createCommand();
        $command->remoteResponses[] = json_encode([
            'ok' => true,
            'result' => [
                'ID' => '123',
                'FILE_NAME' => 'logo.png',
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $output = $command->runRemote($this->createInput(['id' => '123']), 'prod');

        self::assertSame('{"ID":"123","FILE_NAME":"logo.png"}' . PHP_EOL, $output);
        self::assertCount(1, $command->remoteExecutions);
        self::assertSame('prod', $command->remoteExecutions[0]['remote']);
        self::assertStringContainsString('$id = 123;', $command->remoteExecutions[0]['code']);
        self::assertStringContainsString('CFile::GetFileArray($id)', $command->remoteExecutions[0]['code']);
    }

    /** @param array<string, mixed> $arguments */
    private function createInput(array $arguments): ArrayInput
    {
        $input = new ArrayInput($arguments, $this->createCommand()->getDefinition());
        $input->setInteractive(false);

        return $input;
    }

    private function createCommand(): TestableCFileGetCommand
    {
        $reflection = new \ReflectionClass(ClassAliasLoader::class);
        $aliasLoader = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(RemoteProjectConfigManager::class);
        $configManager = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(BitrixAdminClient::class);
        $bitrixAdminClient = $reflection->newInstanceWithoutConstructor();

        return new TestableCFileGetCommand(
            $aliasLoader,
            $configManager,
            $bitrixAdminClient,
            new RemoteFilePhpCodeBuilder(),
        );
    }
}
