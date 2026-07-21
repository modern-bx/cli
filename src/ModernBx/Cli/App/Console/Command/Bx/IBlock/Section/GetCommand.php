<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\IBlock\Section;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteIBlockSectionPhpCodeBuilder;
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

    private RemoteIBlockSectionPhpCodeBuilder $remoteIBlockSectionPhpCodeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteIBlockSectionPhpCodeBuilder $remoteIBlockSectionPhpCodeBuilder
    ) {
        parent::__construct($aliasLoader);

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteIBlockSectionPhpCodeBuilder = $remoteIBlockSectionPhpCodeBuilder;
    }

    protected static $defaultName = 'iblock.section:get';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.iblock_section_get.description'))
            ->setHelp($this->trans('command.iblock_section_get.help'))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputOption('pretty', null, InputOption::VALUE_NONE, $this->trans('option.json.pretty_bx')),
                new InputArgument('ID', InputArgument::REQUIRED, $this->trans('argument.iblock_section.id')),
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
        $id = $this->getSectionId($input);
        $flags = $this->getJsonFlags($input);

        if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new \RuntimeException('Не удалось подключить модуль iblock.');
        }

        if (!class_exists('CIBlockSection')) {
            throw new \RuntimeException('Класс CIBlockSection недоступен.');
        }

        $iblockId = $this->getSectionIBlockId($id);
        $result = \CIBlockSection::GetList(
            [],
            ['ID' => $id, 'IBLOCK_ID' => $iblockId],
            false,
            ['*', 'UF_*'],
            ['nTopCount' => 1]
        );
        $section = $result->GetNext();

        if (!$section) {
            throw new \Exception(
                $this->trans('error.iblock_section.not_found', ['%id%' => (string) $id]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $this->printer->info((string) to_json($this->normalizeTildeFields($section), $flags));
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        $id = $this->getSectionId($input);
        $flags = $this->getJsonFlags($input);

        $fields = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($remote, $this->remoteIBlockSectionPhpCodeBuilder->buildGet($id)),
            'Не удалось получить раздел инфоблока удаленного проекта.'
        );

        if (!is_array($fields)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректные поля раздела инфоблока.');
        }

        /** @var array<string, mixed> $fields */
        $this->printer->info((string) to_json($fields, $flags));
    }


    private function getSectionIBlockId(int $id): int
    {
        /** @phpstan-ignore-next-line */
        $result = \CIBlockSection::GetList([], ['ID' => $id], false, ['ID', 'IBLOCK_ID'], ['nTopCount' => 1]);
        $section = $result->GetNext();

        if (!$section) {
            throw new \Exception(
                $this->trans('error.iblock_section.not_found', ['%id%' => (string) $id]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $iblockId = $section['IBLOCK_ID'] ?? null;
        if ((!is_int($iblockId) && !is_string($iblockId)) || !ctype_digit((string) $iblockId) || (int) $iblockId <= 0) {
            throw new \RuntimeException('Не удалось определить IBLOCK_ID раздела инфоблока.');
        }

        return (int) $iblockId;
    }

    private function getSectionId(InputInterface $input): int
    {
        $id = $input->getArgument('ID');

        if (!is_string($id) || !ctype_digit($id) || (int) $id <= 0) {
            throw new \Exception(
                $this->trans('error.iblock_section.id_positive'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        return (int) $id;
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
