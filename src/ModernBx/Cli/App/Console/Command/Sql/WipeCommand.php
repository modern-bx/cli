<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Sql;

use ModernBx\Cli\App\Service\Sql\MySqlExecutor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WipeCommand extends SqlCommand
{
    protected static $defaultName = 'sql:wipe';

    private MySqlExecutor $executor;

    public function __construct(MySqlExecutor $executor)
    {
        parent::__construct();

        $this->executor = $executor;
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

        $count = $this->executor->wipe($this->getConnectionConfig());
        $this->printer->info($this->trans('message.sql_wipe.done', ['%count%' => (string) $count]));
    }
}
