<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Sql;

use ModernBx\Cli\App\Service\Sql\MySqlExecutor;
use ModernBx\Cli\App\Service\Sql\PgSqlExecutor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WipeCommand extends SqlCommand
{
    protected static $defaultName = 'sql:wipe';

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
            ->setDescription($this->trans('command.sql_wipe.description'))
            ->setHelp($this->trans('command.sql_wipe.help'));
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
        $count = $config['type'] === 'postgres'
            ? $this->pgSqlExecutor->wipe($config)
            : $this->mySqlExecutor->wipe($config);
        $this->printer->info($this->trans('message.sql_wipe.done', ['%count%' => (string) $count]));
    }
}
