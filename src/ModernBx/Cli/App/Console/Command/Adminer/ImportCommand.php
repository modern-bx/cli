<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Adminer;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemoteConfigParameters;
use ModernBx\Cli\App\Service\Vendor\AdminerClient;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportCommand extends AppCommand
{
    protected static $defaultName = 'adminer:import';

    private RemoteProjectConfigManager $remoteProjectConfigManager;
    private BitrixAdminClient $bitrixAdminClient;
    private AdminerClient $adminerClient;

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        AdminerClient $adminerClient
    ) {
        parent::__construct();
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->adminerClient = $adminerClient;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Импортирует SQL-дамп в удаленную базу данных через Adminer.')
            ->addArgument('src', InputArgument::REQUIRED, 'Локальный SQL-дамп (.sql или .gz)')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Пароль HTTP Basic Auth для Adminer')
            ->addOption('db.engine', null, InputOption::VALUE_REQUIRED, 'Движок БД: mysql или pgsql')
            ->addOption('db.database', null, InputOption::VALUE_REQUIRED, 'Имя базы данных')
            ->addOption('db.host', null, InputOption::VALUE_REQUIRED, 'Хост базы данных')
            ->addOption('db.username', null, InputOption::VALUE_REQUIRED, 'Имя пользователя БД')
            ->addOption('db.password', null, InputOption::VALUE_REQUIRED, 'Пароль пользователя БД')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Папка Adminer относительно корня сайта', '/')
            ->addOption('void', null, InputOption::VALUE_NONE, 'Не выводить таблицу результата')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Перезаписать уже загруженный файл дампа')
            ->addOption('no-delete', null, InputOption::VALUE_NONE, 'Не удалять файл дампа после импорта')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Формат вывода: table, csv или json', 'table');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $options = $this->validateInput($input);
        $this->debug('Этап 1/6: загружаю конфигурацию remote "' . $options['remote'] . '".');
        $config = $this->remoteProjectConfigManager->load($options['remote']);
        $databaseOptions = $this->resolveDatabaseOptions(
            $input,
            $this->remoteProjectConfigManager->getOptions($config),
        );
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->getSessionId($options['remote'], $config);

        $this->debug('Этап 2/6: проверяю Adminer и отсутствие файла дампа.');
        $paths = $this->remotePaths($options['path'], $options['dump_filename']);
        $dumpExists = $this->assertRemoteFiles($options['remote'], $config, $endpoint, $sessionId, $paths);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config) ?: $sessionId;

        if ($dumpExists && !$input->getOption('force')) {
            throw new \RuntimeException('Файл дампа уже существует на remote: ' . $paths['dump'] . '.', 1);
        }
        if ($dumpExists && $input->getOption('force')) {
            $this->debug('Опция --force: удаляю предыдущий файл дампа.');
            $this->deleteDump($options['remote'], $config, $endpoint, $sessionId, $paths['dump']);
            $sessionId = $this->remoteProjectConfigManager->getSessionId($config) ?: $sessionId;
        }

        $this->debug('Этап 3/6: загружаю дамп как ' . $paths['dump'] . '.');
        $this->uploadDump(
            $options['remote'],
            $config,
            $endpoint,
            $sessionId,
            $options['src'],
            $options['path'],
            $options['dump_filename'],
        );

        $this->debug('Этап 4/6: начинаю HTTP-сессию Adminer.');
        try {
            $result = $this->adminerClient->import(
                $endpoint,
                $paths['adminer'],
                $options['password'],
                $databaseOptions['db.engine'],
                $databaseOptions['db.database'],
                $databaseOptions['db.host'],
                $databaseOptions['db.username'],
                $databaseOptions['db.password'],
                function (string $message): void {
                    $this->debug($message);
                },
            );
        } finally {
            if (!$input->getOption('no-delete')) {
                $this->debug('Удаляю файл дампа с remote.');
                $this->deleteDump($options['remote'], $config, $endpoint, $sessionId, $paths['dump']);
            }
        }

        $this->debug('Этап 6/6: импорт завершен, обрабатываю результат.');
        if (!$input->getOption('void')) {
            $this->renderResult($output, $options['format'], $result);
        }
    }

    /**
     * @return array{remote: string, src: string, password: string, path: string, format: string,
     *     dump_filename: string}
     */
    private function validateInput(InputInterface $input): array
    {
        $remote = $input->getOption('remote');
        if (!is_string($remote) || trim($remote) === '') {
            throw new \RuntimeException(
                'Команда adminer:import работает только с remote.',
                static::CODE_INVALID_OPTION_VALUE,
            );
        }
        $src = $input->getArgument('src');
        if (!is_string($src) || !is_file($src) || !is_readable($src)) {
            throw new \RuntimeException(
                'Файл дампа не найден или недоступен для чтения.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }
        $lower = strtolower($src);
        if (str_ends_with($lower, '.gz')) {
            $dumpFilename = 'adminer.sql.gz';
        } elseif (str_ends_with($lower, '.sql')) {
            $dumpFilename = 'adminer.sql';
        } else {
            throw new \RuntimeException(
                'Поддерживаются только файлы .sql и .gz.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }
        $password = $input->getOption('password');
        if (!is_string($password) || $password === '') {
            throw new \RuntimeException('Опция --password обязательна.', static::CODE_INVALID_OPTION_VALUE);
        }
        $format = $input->getOption('format');
        if (!is_string($format) || !in_array($format, ['table', 'csv', 'json'], true)) {
            throw new \RuntimeException(
                'Опция --format поддерживает table, csv или json.',
                static::CODE_INVALID_OPTION_VALUE,
            );
        }
        $pathOption = $input->getOption('path');
        if (!is_string($pathOption)) {
            throw new \RuntimeException('Опция --path должна быть строкой.', static::CODE_INVALID_OPTION_VALUE);
        }
        $path = $this->normalizePath($pathOption);

        return [
            'remote' => $remote,
            'src' => $src,
            'password' => $password,
            'path' => $path,
            'format' => $format,
            'dump_filename' => $dumpFilename,
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim(str_replace('\\', '/', $path), '/');
        if (preg_match('#(?:^|/)\.\.?(/|$)#', $path)) {
            throw new \RuntimeException(
                'Путь Adminer не должен содержать . или ...',
                static::CODE_INVALID_OPTION_VALUE,
            );
        }
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * @param array<string, string> $savedOptions
     * @return array<string, string>
     */
    private function resolveDatabaseOptions(InputInterface $input, array $savedOptions): array
    {
        $resolved = [];
        foreach (RemoteConfigParameters::ALL as $name) {
            $override = $input->getOption($name);
            if ($override !== null && !is_string($override)) {
                throw new \RuntimeException(
                    'Опция --' . $name . ' должна быть строкой.',
                    static::CODE_INVALID_OPTION_VALUE,
                );
            }
            $value = is_string($override) && $override !== '' ? $override : ($savedOptions[$name] ?? '');
            if ($value === '') {
                throw new \RuntimeException(
                    'Не хватает параметра --' . $name
                    . ': укажите его в команде или перерегистрируйте remote.',
                    static::CODE_INVALID_OPTION_VALUE,
                );
            }
            $resolved[$name] = $value;
        }
        if (!in_array($resolved['db.engine'], ['mysql', 'pgsql'], true)) {
            throw new \RuntimeException(
                'Опция --db.engine поддерживает только mysql или pgsql.',
                static::CODE_INVALID_OPTION_VALUE,
            );
        }
        return $resolved;
    }

    /** @return array{adminer: string, dump: string} */
    private function remotePaths(string $path, string $dumpFilename): array
    {
        $prefix = $path === '/' ? '' : $path;
        return ['adminer' => $prefix . '/adminer.php', 'dump' => $prefix . '/' . $dumpFilename];
    }

    /** @param array<string, mixed> $config */
    private function getSessionId(string $remote, array &$config): string
    {
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);
        return $sessionId !== '' ? $sessionId : $this->remoteProjectConfigManager->refreshSession($remote, $config);
    }

    /**
     * @param array<string, mixed> $config
     * @param array{adminer: string, dump: string} $paths
     */
    private function assertRemoteFiles(
        string $remote,
        array &$config,
        string $endpoint,
        string $sessionId,
        array $paths
    ): bool {
        $code = '$root = rtrim((string) $_SERVER[\'DOCUMENT_ROOT\'], \'/\');'
            . ' echo json_encode([\'adminer\' => is_file($root . ' . var_export($paths['adminer'], true) . '),'
            . ' \'dump\' => is_file($root . ' . var_export($paths['dump'], true) . ')]);';
        $result = $this->executePhpWithRefresh($remote, $config, $endpoint, $sessionId, $code);
        $files = json_decode($result, true);
        if (!is_array($files) || ($files['adminer'] ?? false) !== true) {
            throw new \RuntimeException('Adminer не найден на remote: ' . $paths['adminer'] . '.', 1);
        }
        return ($files['dump'] ?? false) === true;
    }

    /** @param array<string, mixed> $config */
    private function executePhpWithRefresh(
        string $remote,
        array &$config,
        string $endpoint,
        string $sessionId,
        string $code
    ): string {
        try {
            return $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        } catch (\RuntimeException $error) {
            if ($error->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $error;
            }
            $sessionId = $this->remoteProjectConfigManager->refreshSession($remote, $config);
            return $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        }
    }

    /** @param array<string, mixed> $config */
    private function uploadDump(
        string $remote,
        array &$config,
        string $endpoint,
        string $sessionId,
        string $src,
        string $path,
        string $filename
    ): void {
        try {
            $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $src, $path, $filename);
        } catch (\RuntimeException $error) {
            if ($error->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $error;
            }
            $sessionId = $this->remoteProjectConfigManager->refreshSession($remote, $config);
            $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $src, $path, $filename);
        }
    }

    /** @param array<string, mixed> $config */
    private function deleteDump(
        string $remote,
        array &$config,
        string $endpoint,
        string $sessionId,
        string $path
    ): void {
        try {
            $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, $path);
        } catch (\RuntimeException $error) {
            if ($error->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $error;
            }
            $sessionId = $this->remoteProjectConfigManager->refreshSession($remote, $config);
            $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, $path);
        }
    }

    /** @param array{columns: string[], rows: array<int, array<int, string>>} $result */
    private function renderResult(OutputInterface $output, string $format, array $result): void
    {
        if ($format === 'table') {
            (new Table($output))->setHeaders($result['columns'])->setRows($result['rows'])->render();
            return;
        }
        if ($format === 'json') {
            $output->writeln((string) json_encode(
                $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
            return;
        }
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Не удалось подготовить CSV-вывод.', static::CODE_IO_ERROR);
        }
        fputcsv($stream, $result['columns']);
        foreach ($result['rows'] as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $output->write($csv === false ? '' : $csv);
    }

    private function debug(string $message): void
    {
        $this->printer->info('[adminer:import] ' . $message);
    }
}
