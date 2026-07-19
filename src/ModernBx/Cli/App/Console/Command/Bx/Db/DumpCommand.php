<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Db;

use ModernBx\Cli\App\Service\Db\MySqlDumper;
use ModernBx\Cli\App\Service\Db\PgSqlDumper;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteDbPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends DbCommand
{
    use RemotePhpTrait;

    protected static $defaultName = 'db:dump';

    private MySqlDumper $mySqlDumper;

    private PgSqlDumper $pgSqlDumper;

    private RemoteDbPhpCodeBuilder $remoteDbPhpCodeBuilder;

    public function __construct(
        MySqlDumper $mySqlDumper,
        PgSqlDumper $pgSqlDumper,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteDbPhpCodeBuilder $remoteDbPhpCodeBuilder
    ) {
        parent::__construct();

        $this->mySqlDumper = $mySqlDumper;
        $this->pgSqlDumper = $pgSqlDumper;
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteDbPhpCodeBuilder = $remoteDbPhpCodeBuilder;
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
                    new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                    new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
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
        $file = $input->getArgument('file');

        if (!is_string($file) || $file === '') {
            throw new \Exception($this->trans('error.db_dump.file_string'), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $tables = $this->getTableFilter($input);
        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->executeRemote($remote, $file, $tables);
            $this->printer->info($this->trans('message.db_dump.created', ['%file%' => $file]));
            return;
        }

        parent::executeInternal($input, $output);

        $config = $this->getConnectionConfig();

        if ($config['type'] === 'postgres') {
            $this->pgSqlDumper->dump($config, $file, $tables);
        } else {
            $this->mySqlDumper->dump($config, $file, $tables);
        }
        $this->printer->info($this->trans('message.db_dump.created', ['%file%' => $file]));
    }

    /** @param array<int, string>|null $tables */
    protected function executeRemote(string $remote, string $file, ?array $tables): void
    {
        $directory = dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \Exception('Unable to create dump directory: ' . $directory);
        }

        $json = $this->executeRemotePhp($remote, $this->remoteDbPhpCodeBuilder->buildDump($tables));
        $dump = $this->decodeRemoteJsonResult($json, 'Не удалось создать дамп базы данных удаленного проекта.');

        if (!is_string($dump)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный дамп базы данных.');
        }

        if (file_put_contents($file, $dump) === false) {
            throw new \Exception('Unable to write dump file: ' . $file);
        }
    }
}
