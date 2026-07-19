<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Db;

use ModernBx\Cli\App\Service\Db\MySqlExecutor;
use ModernBx\Cli\App\Service\Db\PgSqlExecutor;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteDbPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WipeCommand extends DbCommand
{
    use RemotePhpTrait;

    protected static $defaultName = 'db:wipe';

    private MySqlExecutor $mySqlExecutor;

    private PgSqlExecutor $pgSqlExecutor;

    private RemoteDbPhpCodeBuilder $remoteDbPhpCodeBuilder;

    public function __construct(
        MySqlExecutor $mySqlExecutor,
        PgSqlExecutor $pgSqlExecutor,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteDbPhpCodeBuilder $remoteDbPhpCodeBuilder
    ) {
        parent::__construct();

        $this->mySqlExecutor = $mySqlExecutor;
        $this->pgSqlExecutor = $pgSqlExecutor;
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteDbPhpCodeBuilder = $remoteDbPhpCodeBuilder;
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
            )
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $tables = $this->getTableFilter($input);
        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $count = $this->executeRemote($remote, $tables);
            $this->printer->info($this->trans('message.db_wipe.done', ['%count%' => (string) $count]));
            return;
        }

        parent::executeInternal($input, $output);

        $config = $this->getConnectionConfig();
        $count = $config['type'] === 'postgres'
            ? $this->pgSqlExecutor->wipe($config, $tables)
            : $this->mySqlExecutor->wipe($config, $tables);
        $this->printer->info($this->trans('message.db_wipe.done', ['%count%' => (string) $count]));
    }

    /** @param array<int, string>|null $tables */
    protected function executeRemote(string $remote, ?array $tables): int
    {
        $json = $this->executeRemotePhp($remote, $this->remoteDbPhpCodeBuilder->buildWipe($tables));
        $count = $this->decodeRemoteJsonResult($json, 'Не удалось очистить базу данных удаленного проекта.');

        if (!is_int($count)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный счетчик очищенных таблиц.');
        }

        return $count;
    }
}
