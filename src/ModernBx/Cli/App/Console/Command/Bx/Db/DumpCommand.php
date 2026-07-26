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
                        InputArgument::OPTIONAL,
                        $this->trans('argument.db_dump.file'),
                    ),
                    new InputOption(
                        'compress',
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Формат архива (поддерживается zip)',
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

        if ($file !== null && (!is_string($file) || $file === '')) {
            throw new \Exception($this->trans('error.db_dump.file_string'), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if ($file !== null) {
            $file = (string) $file;
        }

        $compress = $input->getOption('compress');
        $compress = $this->validateCompression($compress, $file);
        $tables = $this->getTableFilter($input);
        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $dump = $this->executeRemote($remote, $tables);
            $this->writeDump($output, $file, $dump);

            if ($file === null) {
                return;
            }

            $createdFile = $compress !== null ? $this->compressDump($file) : $file;
            $this->printer->info($this->trans('message.db_dump.created', ['%file%' => $createdFile]));
            return;
        }

        parent::executeInternal($input, $output);

        $config = $this->getConnectionConfig();
        $outputFile = $file ?? $this->createTempDumpFile();

        if ($config['type'] === 'postgres') {
            $this->pgSqlDumper->dump($config, $outputFile, $tables);
        } else {
            $this->mySqlDumper->dump($config, $outputFile, $tables);
        }

        if ($file === null) {
            $dump = file_get_contents($outputFile);
            @unlink($outputFile);

            if ($dump === false) {
                throw new \Exception('Unable to read dump file: ' . $outputFile);
            }

            $output->write($dump);
            return;
        }

        $createdFile = $compress !== null ? $this->compressDump($file) : $file;
        $this->printer->info($this->trans('message.db_dump.created', ['%file%' => $createdFile]));
    }

    protected function validateCompression(mixed $compress, mixed $file): ?string
    {
        if ($compress === null) {
            return null;
        }

        if (!is_string($compress) || strtolower($compress) !== 'zip') {
            throw new \Exception(
                'Unsupported compression format. Supported formats: zip.',
                static::CODE_INVALID_OPTION_VALUE,
            );
        }

        if (!is_string($file) || $file === '') {
            throw new \Exception(
                'The dump file must be specified when using --compress.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        return 'zip';
    }

    protected function compressDump(string $file): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('PHP extension ZipArchive is not available.', static::CODE_IO_ERROR);
        }

        $archiveFile = preg_match('/\.sql$/i', $file) === 1
            ? (string) preg_replace('/\.sql$/i', '.zip', $file)
            : $file . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($archiveFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create ZIP archive: ' . $archiveFile, static::CODE_IO_ERROR);
        }

        if (!$zip->addFile($file, basename($file))) {
            $zip->close();
            @unlink($archiveFile);
            throw new \RuntimeException(
                'Unable to add SQL dump to ZIP archive: ' . $archiveFile,
                static::CODE_IO_ERROR,
            );
        }

        if (!$zip->close()) {
            @unlink($archiveFile);
            throw new \RuntimeException(
                'Unable to add SQL dump to ZIP archive: ' . $archiveFile,
                static::CODE_IO_ERROR,
            );
        }

        if (!unlink($file)) {
            @unlink($archiveFile);
            throw new \RuntimeException('Unable to remove SQL dump after compression: ' . $file, static::CODE_IO_ERROR);
        }

        return $archiveFile;
    }

    /** @param array<int, string>|null $tables */
    protected function executeRemote(string $remote, ?array $tables): string
    {
        $json = $this->executeRemotePhp($remote, $this->remoteDbPhpCodeBuilder->buildDump($tables));
        $dump = $this->decodeRemoteJsonResult($json, 'Не удалось создать дамп базы данных удаленного проекта.');

        if (!is_string($dump)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный дамп базы данных.');
        }

        return $dump;
    }

    protected function writeDump(OutputInterface $output, ?string $file, string $dump): void
    {
        if ($file === null) {
            $output->write($dump);
            return;
        }

        $directory = dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \Exception('Unable to create dump directory: ' . $directory);
        }

        if (file_put_contents($file, $dump) === false) {
            throw new \Exception('Unable to write dump file: ' . $file);
        }
    }

    protected function createTempDumpFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'bx-cli-db-dump-');

        if ($file === false) {
            throw new \Exception('Unable to create temporary dump file.');
        }

        return $file;
    }
}
