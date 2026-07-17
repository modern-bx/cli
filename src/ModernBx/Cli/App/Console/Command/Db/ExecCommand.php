<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Db;

use ModernBx\Cli\App\Service\Db\MySqlExecutor;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
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

    private ProjectRegistry $projectRegistry;

    private BitrixAdminClient $bitrixAdminClient;

    public function __construct(
        MySqlExecutor $mySqlExecutor,
        PgSqlExecutor $pgSqlExecutor,
        ProjectRegistry $projectRegistry,
        BitrixAdminClient $bitrixAdminClient
    ) {
        parent::__construct();

        $this->mySqlExecutor = $mySqlExecutor;
        $this->pgSqlExecutor = $pgSqlExecutor;
        $this->projectRegistry = $projectRegistry;
        $this->bitrixAdminClient = $bitrixAdminClient;
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.db_exec.description'))
            ->setHelp($this->trans('command.db_exec.help'))
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Номер страницы результата')
            ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Размер страницы результата', 100);
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
        $config = $this->projectRegistry->load($codename);
        $project = $this->getProjectConfig($config);
        $account = $this->getDefaultAccountConfig($project);
        $endpoint = $this->readString($project, 'endpoint');
        $sessionId = $this->readString($this->getSessionCookieConfig($account), 'value');
        $page = $this->getPositiveIntOption($input, 'page', false);
        $size = $this->getPositiveIntOption($input, 'size', true) ?? 100;

        if ($sessionId === '') {
            $sessionId = $this->refreshRemoteSession($codename, $config, $project, $account);
        }

        try {
            $result = $this->bitrixAdminClient->executeSql($endpoint, $sessionId, $sql, $page, $size);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->refreshRemoteSession($codename, $config, $project, $account);
            $result = $this->bitrixAdminClient->executeSql($endpoint, $sessionId, $sql, $page, $size);
        }

        if ($result['columns'] !== []) {
            $this->renderTable($output, $result['columns'], $result['rows']);
        }
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

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function getProjectConfig(array $config): array
    {
        $data = $config['data'] ?? null;
        $project = is_array($data) ? ($data['project'] ?? null) : null;

        if (!is_array($project)) {
            throw new \RuntimeException('Некорректная конфигурация удаленного проекта.');
        }

        return $project;
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    protected function getDefaultAccountConfig(array $project): array
    {
        $accounts = $project['accounts'] ?? null;
        $account = is_array($accounts) ? ($accounts['default'] ?? null) : null;

        if (!is_array($account)) {
            throw new \RuntimeException('В конфигурации удаленного проекта нет аккаунта default.');
        }

        return $account;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $project
     * @param array<string, mixed> $account
     */
    protected function refreshRemoteSession(string $codename, array &$config, array $project, array $account): string
    {
        $endpoint = $this->readString($project, 'endpoint');
        $login = $this->readString($account, 'login');
        $password = $this->readString($account, 'password');

        if ($endpoint === '' || $login === '' || $password === '') {
            throw new \RuntimeException('Некорректные учетные данные удаленного проекта.');
        }

        $cookie = $this->bitrixAdminClient->login($endpoint, $login, $password);
        $this->writeSessionCookieConfig($config, $cookie);
        $this->projectRegistry->save($codename, $config);

        return $cookie['value'];
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    protected function getSessionCookieConfig(array $account): array
    {
        $cookies = $account['cookies'] ?? [];
        $cookie = is_array($cookies) ? ($cookies['PHPSESSID'] ?? []) : [];

        return is_array($cookie) ? $cookie : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function readString(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $config
     * @param array{value: string, expires: string} $cookie
     */
    protected function writeSessionCookieConfig(array &$config, array $cookie): void
    {
        $data = is_array($config['data'] ?? null) ? $config['data'] : [];
        $project = is_array($data['project'] ?? null) ? $data['project'] : [];
        $accounts = is_array($project['accounts'] ?? null) ? $project['accounts'] : [];
        $default = is_array($accounts['default'] ?? null) ? $accounts['default'] : [];
        $cookies = is_array($default) && is_array($default['cookies'] ?? null) ? $default['cookies'] : [];
        $cookies['PHPSESSID'] = $cookie;
        $default['cookies'] = $cookies;
        $accounts['default'] = $default;
        $project['accounts'] = $accounts;
        $data['project'] = $project;
        $config['data'] = $data;
    }
}
