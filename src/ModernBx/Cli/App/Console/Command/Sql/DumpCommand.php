<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Sql;

use ModernBx\Cli\App\Service\Sql\MySqlDumper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends SqlCommand
{
    protected static $defaultName = 'sql:dump';

    private MySqlDumper $dumper;

    public function __construct(MySqlDumper $dumper)
    {
        parent::__construct();

        $this->dumper = $dumper;
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.sql_dump.description'))
            ->setHelp($this->trans('command.sql_dump.help'))
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'file',
                        InputArgument::REQUIRED,
                        $this->trans('argument.sql_dump.file'),
                    ),
                ]),
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

        $file = $input->getArgument('file');

        if (!is_string($file) || $file === '') {
            throw new \Exception($this->trans('error.sql_dump.file_string'), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $config = $this->getConnectionConfig();
        $this->dumper->dump($config, $file);
        $this->printer->info($this->trans('message.sql_dump.created', ['%file%' => $file]));
    }
}
