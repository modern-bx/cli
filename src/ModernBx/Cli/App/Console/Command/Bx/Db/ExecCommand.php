<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Db;

use ModernBx\Cli\App\Service\Db\MySqlExecutor;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemoteSqlPhpCodeBuilder;
use ModernBx\Cli\App\Service\Db\PgSqlExecutor;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExecCommand extends DbCommand
{
    protected static $defaultName = 'db:exec';

    private MySqlExecutor $mySqlExecutor;

    private PgSqlExecutor $pgSqlExecutor;

    private RemoteProjectConfigManager $remoteProjectConfigManager;

    private BitrixAdminClient $bitrixAdminClient;

    private RemoteSqlPhpCodeBuilder $remoteSqlPhpCodeBuilder;

    public function __construct(
        MySqlExecutor $mySqlExecutor,
        PgSqlExecutor $pgSqlExecutor,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteSqlPhpCodeBuilder $remoteSqlPhpCodeBuilder
    ) {
        parent::__construct();

        $this->mySqlExecutor = $mySqlExecutor;
        $this->pgSqlExecutor = $pgSqlExecutor;
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteSqlPhpCodeBuilder = $remoteSqlPhpCodeBuilder;
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.db_exec.description'))
            ->setHelp($this->trans('command.db_exec.help'))
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Номер страницы результата')
            ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Размер страницы результата', 100)
            ->addOption('php', null, InputOption::VALUE_NONE, 'Выполнить удаленный SQL через PHP-консоль');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($input, $output, $remote);
            return;
        }

        parent::executeInternal($input, $output);
        $this->executeLocal($output);
    }

    protected function executeLocal(OutputInterface $output): void
    {
        $sql = (string) stream_get_contents(STDIN);
        $config = $this->getConnectionConfig();

        if ($config['type'] === 'postgres') {
            $results = $this->pgSqlExecutor->execute($config, $sql);
        } else {
            $results = $this->mySqlExecutor->execute($config, $sql);
        }

        foreach ($results as $result) {
            $this->renderTable($output, $result['columns'], $result['rows']);
        }
    }

    protected function executeRemote(InputInterface $input, OutputInterface $output, string $codename): void
    {
        $sql = (string) stream_get_contents(STDIN);
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);
        $page = $this->getPositiveIntOption($input, 'page', false);
        $size = $this->getPositiveIntOption($input, 'size', true) ?? 100;
        $executeViaPhp = $input->getOption('php') === true;

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        try {
            $result = $executeViaPhp
                ? $this->executeRemoteSqlViaPhp($endpoint, $sessionId, $sql, $page ?? 1, $size)
                : $this->bitrixAdminClient->executeSql($endpoint, $sessionId, $sql, $page, $size);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $result = $executeViaPhp
                ? $this->executeRemoteSqlViaPhp($endpoint, $sessionId, $sql, $page ?? 1, $size)
                : $this->bitrixAdminClient->executeSql($endpoint, $sessionId, $sql, $page, $size);
        }

        if ($result['columns'] !== []) {
            $this->renderTable($output, $result['columns'], $result['rows']);
        }
    }

    /**
     * @return array{columns: string[], rows: array<int, array<int, string>>}
     */
    protected function executeRemoteSqlViaPhp(
        string $endpoint,
        string $sessionId,
        string $sql,
        int $page,
        int $size
    ): array {
        $code = $this->remoteSqlPhpCodeBuilder->build($sql, $page, $size);

        return $this->bitrixAdminClient->executeSqlPhp($endpoint, $sessionId, $code);
    }

    /**
     * @param string[] $columns
     * @param array<int, array<int, mixed>> $rows
     */
    protected function renderTable(OutputInterface $output, array $columns, array $rows): void
    {
        $table = new Table($output);
        $table
            ->setHeaders($columns)
            ->setRows($rows)
            ->render();
    }

    protected function getPositiveIntOption(InputInterface $input, string $name, bool $required): ?int
    {
        $value = $input->getOption($name);

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            if ($value < 1) {
                throw new \RuntimeException(sprintf('Опция --%s должна быть положительным целым числом.', $name));
            }

            return $value;
        }

        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            throw new \RuntimeException(sprintf('Опция --%s должна быть положительным целым числом.', $name));
        }

        return (int) $value;
    }
}
