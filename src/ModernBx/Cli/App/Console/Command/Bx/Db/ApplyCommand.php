<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Db;

use ModernBx\Cli\App\Service\Db\MySqlExecutor;
use ModernBx\Cli\App\Service\Db\PgSqlExecutor;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteDbPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Helper\Table;
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
                        InputArgument::OPTIONAL,
                        $this->trans('argument.db_apply.file'),
                    ),
                    new InputOption(
                        'format',
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Формат вывода: table, json или csv',
                        'table',
                    ),
                    new InputOption('void', null, InputOption::VALUE_NONE, 'Не выводить результаты SQL-скриптов'),
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

        if ($file !== null && (!is_string($file) || $file === '')) {
            throw new \Exception($this->trans('error.db_apply.file_string'), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $format = $this->getOutputFormat($input->getOption('format'));
        $void = $input->getOption('void') === true;
        $scripts = $this->readSqlScripts(is_string($file) ? $file : null);
        $remote = $input->getOption('remote');
        $config = [];

        if (!is_string($remote)) {
            parent::executeInternal($input, $output);
            $config = $this->getConnectionConfig();
        }

        foreach ($scripts as $script) {
            if ($script['name'] !== null) {
                $output->writeln('[FILE] ' . $script['name']);
            }

            if (trim($script['sql']) === '') {
                continue;
            }

            $results = is_string($remote)
                ? $this->executeRemote($remote, $script['sql'])
                : $this->executeLocal($config, $script['sql']);

            if (!$void) {
                $this->renderResults($output, $format, $results);
            }
        }

        if ($this->shouldPrintCompletionMessage($format)) {
            $this->printer->info($this->trans('message.db_apply.applied', ['%file%' => $file ?? 'stdin']));
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, array{columns: array<int, string>, rows: array<int, array<int, string|null>>}>
     */
    protected function executeLocal(array $config, string $sql): array
    {
        return $config['type'] === 'postgres'
            ? $this->pgSqlExecutor->execute($config, $sql)
            : $this->mySqlExecutor->execute($config, $sql);
    }

    /** @return array<int, array{columns: array<int, string>, rows: array<int, array<int, string|null>>}> */
    protected function executeRemote(string $remote, string $sql): array
    {
        $json = $this->executeRemotePhp($remote, $this->remoteDbPhpCodeBuilder->buildApply($sql));
        $result = $this->decodeRemoteJsonResult($json, 'Не удалось применить SQL-файл на удаленном проекте.');

        return is_array($result) ? $result : [];
    }

    protected function readSql(?string $file): string
    {
        if ($file === null) {
            return (string) stream_get_contents(STDIN);
        }

        return implode("\n", array_column($this->readSqlScripts($file), 'sql'));
    }

    /** @return array<int, array{name: string|null, sql: string}> */
    protected function readSqlScripts(?string $file): array
    {
        if ($file === null) {
            return [['name' => null, 'sql' => (string) stream_get_contents(STDIN)]];
        }

        $scripts = [];
        foreach ($this->resolveSqlPaths($file) as $path) {
            if ($this->isZipPath($path)) {
                foreach ($this->readZipSqlFiles($path) as $name => $sql) {
                    $scripts[] = ['name' => $this->relativePath($path, $file) . '/' . $name, 'sql' => $sql];
                }
                continue;
            }

            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new \Exception('Unable to read SQL file: ' . $path);
            }
            $scripts[] = ['name' => $this->relativePath($path, $file), 'sql' => $sql];
        }

        if (count($scripts) === 1 && is_string($scripts[0]['name'])) {
            $scripts[0]['name'] = (string) preg_replace(
                '#^(?:.*/)?[^/]+\.zip/(.+)$#i',
                '$1',
                $scripts[0]['name'],
            );
        }

        return $scripts;
    }

    protected function relativePath(string $path, string $expression): string
    {
        if (is_file($expression)) {
            return basename($path);
        }

        $base = is_dir($expression) ? $expression : $this->globBaseDirectory($expression);
        $base = rtrim(str_replace('\\', '/', $base), '/');
        $normalizedPath = str_replace('\\', '/', $path);

        return str_starts_with($normalizedPath, $base . '/')
            ? substr($normalizedPath, strlen($base) + 1)
            : basename($path);
    }

    protected function globBaseDirectory(string $expression): string
    {
        $wildcard = strcspn($expression, '*?{[');
        $prefix = substr($expression, 0, $wildcard);
        $separator = max((int) strrpos($prefix, '/'), (int) strrpos($prefix, '\\'));

        return $separator > 0 ? substr($prefix, 0, $separator) : '.';
    }

    /** @return array<int, string> */
    protected function resolveSqlPaths(string $expression): array
    {
        if (is_dir($expression)) {
            $paths = glob(rtrim($expression, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*') ?: [];
        } elseif (is_file($expression)) {
            $paths = [$expression];
        } else {
            $paths = glob($expression, GLOB_BRACE) ?: [];
        }

        $paths = array_values(array_filter(
            $paths,
            fn (string $path): bool => is_file($path)
                && is_readable($path)
                && (preg_match('/\.sql$/i', $path) === 1 || $this->isZipPath($path)),
        ));
        sort($paths, SORT_STRING);

        if ($paths === []) {
            throw new \Exception('No readable SQL files or ZIP archives found: ' . $expression);
        }

        return $paths;
    }

    protected function isZipPath(string $path): bool
    {
        return preg_match('/\.zip$/i', $path) === 1;
    }

    /** @return array<int, string> */
    protected function readZipSql(string $path): array
    {
        return array_values($this->readZipSqlFiles($path));
    }

    /** @return array<string, string> */
    protected function readZipSqlFiles(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('PHP extension ZipArchive is not available.', static::CODE_IO_ERROR);
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \Exception('Unable to open ZIP archive: ' . $path);
        }

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (!is_string($name) || str_contains(str_replace('\\', '/', $name), '/')) {
                continue;
            }

            if (preg_match('/\.sql$/i', $name) === 1) {
                $entries[$name] = $index;
            }
        }

        ksort($entries, SORT_STRING);
        $sql = [];

        foreach ($entries as $name => $index) {
            $contents = $zip->getFromIndex($index);

            if ($contents === false) {
                $zip->close();
                throw new \Exception('Unable to extract SQL file from ZIP archive: ' . $name);
            }

            $sql[$name] = $contents;
        }

        $zip->close();

        return $sql;
    }

    protected function shouldPrintCompletionMessage(string $format): bool
    {
        return $format === 'table';
    }

    protected function getOutputFormat(mixed $format): string
    {
        if (!is_string($format) || !in_array($format, ['table', 'json', 'csv'], true)) {
            throw new \RuntimeException(
                'Опция --format поддерживает table, json или csv.',
                static::CODE_INVALID_OPTION_VALUE,
            );
        }

        return $format;
    }

    /**
     * @param array<int, array{columns: array<int, string>, rows: array<int, array<int, string|null>>}> $results
     */
    protected function renderResults(OutputInterface $output, string $format, array $results): void
    {
        foreach ($results as $result) {
            if ($format === 'table') {
                (new Table($output))->setHeaders($result['columns'])->setRows($result['rows'])->render();
                continue;
            }

            if ($format === 'json') {
                $output->writeln((string) json_encode(
                    $result,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ));
                continue;
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
    }
}
