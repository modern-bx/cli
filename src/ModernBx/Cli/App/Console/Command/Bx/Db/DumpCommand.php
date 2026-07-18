<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Db;

use ModernBx\Cli\App\Service\Db\MySqlDumper;
use ModernBx\Cli\App\Service\Db\PgSqlDumper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends DbCommand
{
    protected static $defaultName = 'db:dump';

    private MySqlDumper $mySqlDumper;

    private PgSqlDumper $pgSqlDumper;

    public function __construct(MySqlDumper $mySqlDumper, PgSqlDumper $pgSqlDumper)
    {
        parent::__construct();

        $this->mySqlDumper = $mySqlDumper;
        $this->pgSqlDumper = $pgSqlDumper;
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.db_dump.description'))
            ->setHelp($this->trans('command.db_dump.help'))
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'file',
                        InputArgument::REQUIRED,
                        $this->trans('argument.db_dump.file'),
                    ),
                    new InputOption(
                        'table',
                        null,
                        InputOption::VALUE_REQUIRED,
                        $this->trans('option.db.table'),
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
            throw new \Exception($this->trans('error.db_dump.file_string'), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $config = $this->getConnectionConfig();
        $tables = $this->getTableFilter($input);

        if ($config['type'] === 'postgres') {
            $this->pgSqlDumper->dump($config, $file, $tables);
        } else {
            $this->mySqlDumper->dump($config, $file, $tables);
        }
        $this->printer->info($this->trans('message.db_dump.created', ['%file%' => $file]));
    }
}
