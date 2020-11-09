<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Console\Mixin\IO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CacheClearCommand extends BxCommand
{
    use IO;

    /**
     * @var string
     */
    protected static $defaultName = 'cache:clear';

    protected function configure(): void
    {
        $this
            ->setDescription("Clear Bitrix cache")
            ->setHelp("Delete the contents of 'cache', 'managed_cache' or 'stack_cache'")
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'directory',
                        InputArgument::IS_ARRAY,
                        "Cache directories to be cleaned up",
                    ),
                ])
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        /** @var array<string> $directories */
        $directories = $input->getArgument("directory") ?: $this->getDefaultDirectories();

        foreach ($directories as $directory) {
            if (!in_array($directory, $this->getValidDirectories())) {
                throw new \Exception("Invalid cache directory", static::CODE_INVALID_ARGUMENT_VALUE);
            }
        }

        foreach ($directories as $directory) {
            $path = $this->bxRoot->pushPathSegment($directory)->toString();

            if (file_exists($path) && is_dir($path)) {
                $this->deleteDirectory($path);
            }
        }
    }

    /**
     * @return string[]
     */
    protected function getDefaultDirectories(): array
    {
        return [
            "cache",
            "managed_cache",
            "stack_cache",
        ];
    }

    /**
     * @return string[]
     */
    protected function getValidDirectories(): array
    {
        return [
            "cache",
            "managed_cache",
            "stack_cache",
        ];
    }
}
