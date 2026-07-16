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
            ->setDescription($this->trans("command.env_get.description"))
            ->setHelp($this->trans("command.env_get.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'file',
                        InputArgument::REQUIRED,
                        $this->trans("argument.env.file"),
                    ),
                    new InputArgument(
                        'key',
                        InputArgument::REQUIRED,
                        $this->trans("argument.env.key"),
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
            throw new \RuntimeException($this->trans("error.dotenv.read"), static::CODE_IO_ERROR);
        }

        $this->printer->info((string) EnvFile::get($content, $key));
    }
}
