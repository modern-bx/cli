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

use function ModernBx\CommonFunctions\to_json;

class GetCommand extends KernelCommand
{
    use RemotePhpTrait;

    protected static $defaultName = 'cfile:get';

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
            ->setDescription('Возвращает файл из b_file через CFile::GetFileArray')
            ->setHelp('Возвращает JSON с результатом CFile::GetFileArray() для указанного ID файла.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addArgument('id', InputArgument::REQUIRED, 'ID файла в таблице b_file');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $idArgument = $input->getArgument('id');

        if (!is_string($idArgument) && !is_int($idArgument)) {
            throw new \RuntimeException('Аргумент ID должен быть числом.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $id = $this->normalizeId((string) $idArgument);

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $row = $this->executeRemote($remote, $id);
        } else {
            parent::executeInternal($input, $output);
            $row = $this->executeLocal($id);
        }

        $this->printer->info($this->encodeFileRow($row));
    }

    /** @return array<string, mixed> */
    protected function executeLocal(int $id): array
    {
        if (!class_exists('CFile')) {
            throw new \RuntimeException('Класс CFile недоступен на проекте.');
        }

        $row = \CFile::GetFileArray($id);

        if (!is_array($row)) {
            throw new \RuntimeException(sprintf('Файл с ID %d не найден в b_file.', $id), static::CODE_IO_ERROR);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    protected function executeRemote(string $codename, int $id): array
    {
        $result = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($codename, $this->remoteFilePhpCodeBuilder->buildCFileGet($id)),
            'Не удалось получить файл удаленного проекта из b_file.',
        );

        if (!is_array($result)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный результат CFile::GetFileArray().');
        }

        return $result;
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

    /** @param array<string, mixed> $row */
    protected function encodeFileRow(array $row): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return (string) to_json($row, $flags);
    }
}
