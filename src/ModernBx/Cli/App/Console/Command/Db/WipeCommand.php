<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Db;

use ModernBx\Cli\App\Service\Db\MySqlExecutor;
use ModernBx\Cli\App\Service\Db\PgSqlExecutor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WipeCommand extends DbCommand
{
    protected static $defaultName = 'db:wipe';

    private MySqlExecutor $mySqlExecutor;

    private PgSqlExecutor $pgSqlExecutor;

    public function __construct(MySqlExecutor $mySqlExecutor, PgSqlExecutor $pgSqlExecutor)
    {
        parent::__construct();

        $this->mySqlExecutor = $mySqlExecutor;
        $this->pgSqlExecutor = $pgSqlExecutor;
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.db_wipe.description'))
            ->setHelp($this->trans('command.db_wipe.help'))
            ->addOption(
                'table',
                null,
                InputOption::VALUE_REQUIRED,
                $this->trans('option.db.table'),
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

        $config = $this->getConnectionConfig();
        $tables = $this->getTableFilter($input);
        $count = $config['type'] === 'postgres'
            ? $this->pgSqlExecutor->wipe($config, $tables)
            : $this->mySqlExecutor->wipe($config, $tables);
        $this->printer->info($this->trans('message.db_wipe.done', ['%count%' => (string) $count]));
    }
}
