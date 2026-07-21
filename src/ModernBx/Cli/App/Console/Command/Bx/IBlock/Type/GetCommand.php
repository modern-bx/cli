<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\IBlock\Type;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteIBlockTypePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\to_json;

class GetCommand extends KernelCommand
{
    use IO;
    use RemotePhpTrait;

    private RemoteIBlockTypePhpCodeBuilder $remoteIBlockTypePhpCodeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteIBlockTypePhpCodeBuilder $remoteIBlockTypePhpCodeBuilder
    ) {
        parent::__construct($aliasLoader);

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteIBlockTypePhpCodeBuilder = $remoteIBlockTypePhpCodeBuilder;
    }

    protected static $defaultName = 'iblock.type:get';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.iblock_type_get.description'))
            ->setHelp($this->trans('command.iblock_type_get.help'))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputOption('pretty', null, InputOption::VALUE_NONE, $this->trans('option.json.pretty_bx')),
                new InputArgument('ID', InputArgument::REQUIRED, $this->trans('argument.iblock_type.id')),
            ]));
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($input, $remote);
            return;
        }

        parent::executeInternal($input, $output);
        $this->executeLocal($input);
    }

    protected function executeLocal(InputInterface $input): void
    {
        $id = $this->getIBlockTypeId($input);
        $flags = $this->getJsonFlags($input);

        if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new \RuntimeException('Не удалось подключить модуль iblock.');
        }

        if (!class_exists('CIBlockType')) {
            throw new \RuntimeException('Класс CIBlockType недоступен.');
        }

        $result = \CIBlockType::GetList([], ['=ID' => $id]);
        $iblockType = $result->Fetch();

        if (!$iblockType) {
            throw new \Exception(
                $this->trans('error.iblock_type.not_found', ['%id%' => (string) $id]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $this->printer->info((string) to_json($this->normalizeTildeFields($iblockType), $flags));
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        $id = $this->getIBlockTypeId($input);
        $flags = $this->getJsonFlags($input);

        $fields = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($remote, $this->remoteIBlockTypePhpCodeBuilder->buildGet($id)),
            'Не удалось получить тип инфоблока удаленного проекта.'
        );

        if (!is_array($fields)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректные поля типа инфоблока.');
        }

        /** @var array<string, mixed> $fields */
        $this->printer->info((string) to_json($fields, $flags));
    }


    private function getIBlockTypeId(InputInterface $input): string
    {
        $id = $input->getArgument('ID');

        if (!is_string($id) || trim($id) === '') {
            throw new \Exception(
                $this->trans('error.iblock_type.id_required'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        return trim($id);
    }

    private function getJsonFlags(InputInterface $input): int
    {
        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return $flags;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function normalizeTildeFields(array $fields): array
    {
        foreach (array_keys($fields) as $field) {
            if (!is_string($field) || !str_starts_with($field, '~')) {
                continue;
            }

            $normalizedField = substr($field, 1);
            if ($normalizedField === '') {
                unset($fields[$field]);
                continue;
            }

            $fields[$normalizedField] = $fields[$field];
            unset($fields[$field]);
        }

        return $fields;
    }
}
