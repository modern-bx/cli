<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Site;

use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemoteSitePhpCodeBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

final class ListCommandTest extends TestCase
{
    public function testRemoteShortUsesDefaultTemplate(): void
    {
        $command = $this->createCommand();
        $command->remoteResponses[] = json_encode([
            'ok' => true,
            'result' => [[
                'LID' => 's1',
                'NAME' => 'Какой-то сайт',
                'SERVER_NAME' => 'server.local',
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $output = $command->runRemote($this->createInput(['--short' => null]), 'prod');

        self::assertSame('[s1] Какой-то сайт [server.local]' . PHP_EOL, $output);
    }

    public function testRemoteShortKeepsTemplateTextAndSubstitutesPhpStyleVariables(): void
    {
        $command = $this->createCommand();
        $command->remoteResponses[] = json_encode([
            'ok' => true,
            'result' => [[
                'LID' => 's1',
                'NAME' => 'Сайт',
                'SERVER_NAME' => 'https://server.local',
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $output = $command->runRemote($this->createInput([
            '--short' => '[$LID] literal [NAME] $SERVER_NAME',
        ]), 'prod');

        self::assertSame('[s1] literal [NAME] https://server.local' . PHP_EOL, $output);
    }

    /** @param array<string, mixed> $arguments */
    private function createInput(array $arguments): ArrayInput
    {
        $input = new ArrayInput($arguments, $this->createCommand()->getDefinition());
        $input->setInteractive(false);

        return $input;
    }

    private function createCommand(): TestableSiteListCommand
    {
        $reflection = new \ReflectionClass(ClassAliasLoader::class);
        $aliasLoader = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(RemoteProjectConfigManager::class);
        $configManager = $reflection->newInstanceWithoutConstructor();
        $reflection = new \ReflectionClass(BitrixAdminClient::class);
        $client = $reflection->newInstanceWithoutConstructor();

        return new TestableSiteListCommand(
            $aliasLoader,
            $configManager,
            $client,
            new RemoteSitePhpCodeBuilder(),
        );
    }
}
