<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command;

use ModernBx\Cli\App\Service\EnvFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvGetCommand extends AppCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'env:get';

    protected function configure(): void
    {
        $this
            ->setDescription("Get a value from dotenv file")
            ->setHelp("Reads a dotenv file and prints the decoded value for the specified key.")
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'file',
                        InputArgument::REQUIRED,
                        "Path to dotenv file",
                    ),
                    new InputArgument(
                        'key',
                        InputArgument::REQUIRED,
                        "Environment variable name",
                    ),
                ]),
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \RuntimeException
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        /** @var string $file */
        $file = $input->getArgument("file");
        /** @var string $key */
        $key = $input->getArgument("key");
        $content = file_get_contents($file);

        if ($content === false) {
            throw new \RuntimeException("Unable to read dotenv file.", static::CODE_IO_ERROR);
        }

        $this->printer->info((string) EnvFile::get($content, $key));
    }
}
