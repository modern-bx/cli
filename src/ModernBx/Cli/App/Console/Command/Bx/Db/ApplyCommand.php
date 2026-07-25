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
                        InputArgument::OPTIONAL,
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

        if ($file !== null && (!is_string($file) || $file === '')) {
            throw new \Exception($this->trans('error.db_apply.file_string'), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if ($file !== null) {
            $file = (string) $file;
        }

        $sql = $this->readSql($file);

        if (trim($sql) === '') {
            $this->printer->put('SQL input is empty.', 'comment');
            return;
        }

        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->executeRemote($remote, $sql);
            $this->printer->info($this->trans('message.db_apply.applied', ['%file%' => $file ?? 'stdin']));
            return;
        }

        parent::executeInternal($input, $output);

        $config = $this->getConnectionConfig();

        if ($config['type'] === 'postgres') {
            $this->pgSqlExecutor->execute($config, $sql);
        } else {
            $this->mySqlExecutor->execute($config, $sql);
        }
        $this->printer->info($this->trans('message.db_apply.applied', ['%file%' => $file ?? 'stdin']));
    }

    protected function executeRemote(string $remote, string $sql): void
    {
        $json = $this->executeRemotePhp($remote, $this->remoteDbPhpCodeBuilder->buildApply($sql));
        $this->decodeRemoteJsonResult($json, 'Не удалось применить SQL-файл на удаленном проекте.');
    }

    protected function readSql(?string $file): string
    {
        if ($file === null) {
            return (string) stream_get_contents(STDIN);
        }

        $paths = $this->resolveSqlPaths($file);
        $parts = [];

        foreach ($paths as $path) {
            if ($this->isZipPath($path)) {
                array_push($parts, ...$this->readZipSql($path));
                continue;
            }

            $sql = file_get_contents($path);

            if ($sql === false) {
                throw new \Exception('Unable to read SQL file: ' . $path);
            }

            $parts[] = $sql;
        }

        return implode("\n", $parts);
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

            $sql[] = $contents;
        }

        $zip->close();

        return $sql;
    }
}
