<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Console\Command\Bx\Backup\Internal\Restore\RestoreTarExtractor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ExtractCommand extends AppCommand
{
    /** @var string */
    protected static $defaultName = 'backup:extract';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.backup_extract.description'))
            ->setDefinition(new InputDefinition([
                new InputArgument('archive', InputArgument::REQUIRED, 'Archive path'),
                new InputArgument('destination', InputArgument::REQUIRED, 'Destination directory'),
                new InputOption('password', null, InputOption::VALUE_OPTIONAL, 'Archive password'),
            ]));
    }

    protected function shouldIgnoreSessionRemote(): bool
    {
        return true;
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        $archive = $this->readRequiredArgument($input, 'archive');
        $destination = $this->readRequiredArgument($input, 'destination');
        $password = $input->getOption('password');
        if ($password !== null && !is_string($password)) {
            throw new \InvalidArgumentException('Password must be a string.', static::CODE_INVALID_OPTION_VALUE);
        }

        $result = (new RestoreTarExtractor($password))->extract($archive, $destination);
        $output->writeln((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function readRequiredArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("Argument {$name} is required.", static::CODE_INVALID_OPTION_VALUE);
        }

        return $value;
    }
}
