<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Sql;

use ModernBx\Cli\App\Service\Sql\MySqlExecutor;
use ModernBx\Cli\App\Service\Sql\PgSqlExecutor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ApplyCommand extends SqlCommand
{
    protected static $defaultName = 'sql:apply';

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
            ->setDescription($this->trans('command.sql_apply.description'))
            ->setHelp($this->trans('command.sql_apply.help'))
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'file',
                        InputArgument::REQUIRED,
                        $this->trans('argument.sql_apply.file'),
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
            throw new \Exception($this->trans('error.sql_apply.file_string'), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $config = $this->getConnectionConfig();

        if ($config['type'] === 'postgres') {
            $this->pgSqlExecutor->apply($config, $file);
        } else {
            $this->mySqlExecutor->apply($config, $file);
        }
        $this->printer->info($this->trans('message.sql_apply.applied', ['%file%' => $file]));
    }
}
