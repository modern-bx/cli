<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Db;

use ModernBx\Cli\App\Service\Db\MySqlExecutor;
use ModernBx\Cli\App\Service\Db\PgSqlExecutor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExecCommand extends DbCommand
{
    protected static $defaultName = 'db:exec';

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
            ->setDescription($this->trans('command.db_exec.description'))
            ->setHelp($this->trans('command.db_exec.help'));
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

        $sql = (string) stream_get_contents(STDIN);
        $config = $this->getConnectionConfig();

        if ($config['type'] === 'postgres') {
            $this->pgSqlExecutor->execute($config, $sql);
        } else {
            $this->mySqlExecutor->execute($config, $sql);
        }
    }
}
