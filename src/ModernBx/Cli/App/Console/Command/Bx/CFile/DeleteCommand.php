<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\CFile;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteFilePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCommand extends KernelCommand
{
    use RemotePhpTrait;

    protected static $defaultName = 'cfile:delete';

    protected RemoteFilePhpCodeBuilder $remoteFilePhpCodeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteFilePhpCodeBuilder $remoteFilePhpCodeBuilder
    ) {
        parent::__construct($aliasLoader);

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteFilePhpCodeBuilder = $remoteFilePhpCodeBuilder;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Удаляет файл из b_file через CFile::Delete')
            ->setHelp('Удаляет файл по ID через CFile::Delete(), включая удаление файла с диска.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Не считать ошибкой отсутствие ID в b_file')
            ->addArgument('id', InputArgument::REQUIRED, 'ID файла в таблице b_file');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $idArgument = $input->getArgument('id');
        $force = $input->getOption('force') === true;

        if (!is_string($idArgument) && !is_int($idArgument)) {
            throw new \RuntimeException('Аргумент ID должен быть числом.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $id = $this->normalizeId((string) $idArgument);

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $deleted = $this->executeRemote($remote, $id, $force);
        } else {
            parent::executeInternal($input, $output);
            $deleted = $this->executeLocal($id, $force);
        }

        if ($deleted) {
            $this->printer->info(sprintf('Файл удален из b_file: %d', $id));
        }
    }

    protected function executeLocal(int $id, bool $force): bool
    {
        if (!$this->fileExists($id)) {
            if ($force) {
                return false;
            }

            throw new \RuntimeException(sprintf('Файл с ID %d не найден в b_file.', $id), static::CODE_IO_ERROR);
        }

        /** @phpstan-ignore-next-line */
        \CFile::Delete($id);

        return true;
    }

    protected function executeRemote(string $codename, int $id, bool $force): bool
    {
        $result = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($codename, $this->remoteFilePhpCodeBuilder->buildCFileDelete($id, $force)),
            'Не удалось удалить файл удаленного проекта из b_file.',
        );

        return $result === true;
    }

    protected function normalizeId(string $value): int
    {
        $value = trim($value);

        if ($value === '' || !ctype_digit($value)) {
            throw new \RuntimeException(
                'ID файла должен быть положительным числом.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $id = (int) $value;

        if ($id <= 0) {
            throw new \RuntimeException(
                'ID файла должен быть положительным числом.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        return $id;
    }

    protected function fileExists(int $id): bool
    {
        global $DB;

        $result = $DB->Query('SELECT ID FROM b_file WHERE ID = ' . $id);
        $row = is_object($result) && method_exists($result, 'Fetch') ? $result->Fetch() : false;

        return is_array($row);
    }
}
