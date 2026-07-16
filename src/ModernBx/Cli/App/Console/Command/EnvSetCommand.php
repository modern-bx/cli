<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command;

use ModernBx\Cli\App\Service\EnvFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvSetCommand extends AppCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'env:set';

    protected function configure(): void
    {
        $this
            ->setDescription("Set a value in dotenv file")
            ->setHelp("Updates or appends a key-value pair in a dotenv file and prints the resulting content.")
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
                    new InputArgument(
                        'value',
                        InputArgument::REQUIRED,
                        "Environment variable value",
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
        /** @var string $value */
        $value = $input->getArgument("value");
        $content = "";

        if (is_file($file)) {
            $content = file_get_contents($file);

            if ($content === false) {
                throw new \RuntimeException("Unable to read dotenv file.", static::CODE_IO_ERROR);
            }
        }

        $content = EnvFile::set($content, $key, $value);

        if (file_put_contents($file, $content) === false) {
            throw new \RuntimeException("Unable to write dotenv file.", static::CODE_IO_ERROR);
        }

        $this->printer->info($content);
    }
}
