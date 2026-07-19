<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Db;

use ModernBx\Cli\App\Service\Db\MySqlExecutor;
use ModernBx\Cli\App\Service\Db\PgSqlExecutor;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteDbPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ApplyCommand extends DbCommand
{
    use RemotePhpTrait;

    protected static $defaultName = 'db:apply';

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
            ->setDescription($this->trans('command.db_apply.description'))
            ->setHelp($this->trans('command.db_apply.help'))
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'file',
                        InputArgument::REQUIRED,
                        $this->trans('argument.db_apply.file'),
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
            throw new \Exception($this->trans('error.db_apply.file_string'), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->executeRemote($remote, $file);
            $this->printer->info($this->trans('message.db_apply.applied', ['%file%' => $file]));
            return;
        }

        parent::executeInternal($input, $output);

        $config = $this->getConnectionConfig();

        if ($config['type'] === 'postgres') {
            $this->pgSqlExecutor->apply($config, $file);
        } else {
            $this->mySqlExecutor->apply($config, $file);
        }
        $this->printer->info($this->trans('message.db_apply.applied', ['%file%' => $file]));
    }

    protected function executeRemote(string $remote, string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \Exception('SQL file is not readable: ' . $file);
        }

        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new \Exception('Unable to read SQL file: ' . $file);
        }

        $json = $this->executeRemotePhp($remote, $this->remoteDbPhpCodeBuilder->buildApply($sql));
        $this->decodeRemoteJsonResult($json, 'Не удалось применить SQL-файл на удаленном проекте.');
    }
}
