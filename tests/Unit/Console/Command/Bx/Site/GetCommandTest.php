<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Site;

use Bitrix\Main\SiteTable;
use ModernBx\Cli\App\Console\Command\Bx\Site\GetCommand;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemoteSitePhpCodeBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

final class GetCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        SiteTable::$lastQuery = null;
        SiteTable::$nextRow = null;
    }

    public function testLocalModeGetsSiteWithoutRemoteRequest(): void
    {
        $command = $this->createCommand();
        SiteTable::$nextRow = [
            'LID' => 's1',
            'NAME' => 'Основной сайт',
            'DIR' => '/',
        ];

        $output = $command->runLocal($this->createInput([
            'id' => 's1',
            '--select' => '["LID","NAME","DIR"]',
        ]));

        self::assertSame([
            'filter' => ['=LID' => 's1'],
            'limit' => 1,
            'select' => ['LID', 'NAME', 'DIR'],
        ], SiteTable::$lastQuery);
        self::assertSame('{"LID":"s1","NAME":"Основной сайт","DIR":"\\/"}' . PHP_EOL, $output);
        self::assertSame([], $command->remoteExecutions);
    }

    public function testLocalModeDoesNotPrintMissingSite(): void
    {
        $command = $this->createCommand();
        SiteTable::$nextRow = false;

        $output = $command->runLocal($this->createInput(['id' => 'unknown']));

        self::assertSame([
            'filter' => ['=LID' => 'unknown'],
            'limit' => 1,
        ], SiteTable::$lastQuery);
        self::assertSame('', $output);
    }

    public function testRemoteModeBuildsPhpSnippetAndPrintsMockedResponse(): void
    {
        $command = $this->createCommand();
        $command->remoteResponses[] = json_encode([
            'ok' => true,
            'result' => '{"LID":"s1","NAME":"Remote site"}',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $output = $command->runRemote($this->createInput([
            'id' => 's1',
            '--select' => '["LID","NAME"]',
            '--pretty' => true,
        ]), 'prod');

        self::assertSame('{"LID":"s1","NAME":"Remote site"}' . PHP_EOL, $output);
        self::assertCount(1, $command->remoteExecutions);
        self::assertSame('prod', $command->remoteExecutions[0]['remote']);
        self::assertStringContainsString(
            "'filter' => \n  array (\n    '=LID' => 's1'",
            $command->remoteExecutions[0]['code'],
        );
        self::assertStringContainsString(
            "'select' => \n  array (\n    0 => 'LID',\n    1 => 'NAME'",
            $command->remoteExecutions[0]['code'],
        );
        self::assertStringContainsString(
            '$jsonFlags = ' . (JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . ';',
            $command->remoteExecutions[0]['code'],
        );
        self::assertNull(SiteTable::$lastQuery, 'Remote mode must not call local Bitrix SiteTable.');
    }

    public function testRemoteModeDoesNotPrintNullResult(): void
    {
        $command = $this->createCommand();
        $command->remoteResponses[] = '{"ok":true,"result":null}';

        $output = $command->runRemote($this->createInput(['id' => 'missing']), 'prod');

        self::assertSame('', $output);
        self::assertCount(1, $command->remoteExecutions);
        self::assertNull(SiteTable::$lastQuery);
    }

    /** @param array<string, mixed> $arguments */
    private function createInput(array $arguments): ArrayInput
    {
        $input = new ArrayInput($arguments, $this->createCommand()->getDefinition());
        $input->setInteractive(false);

        return $input;
    }

    private function createCommand(): TestableSiteGetCommand
    {
        $reflection = new \ReflectionClass(ClassAliasLoader::class);
        $aliasLoader = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(RemoteProjectConfigManager::class);
        $configManager = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(BitrixAdminClient::class);
        $client = $reflection->newInstanceWithoutConstructor();

        return new TestableSiteGetCommand(
            $aliasLoader,
            $configManager,
            $client,
            new RemoteSitePhpCodeBuilder(),
        );
    }
}
