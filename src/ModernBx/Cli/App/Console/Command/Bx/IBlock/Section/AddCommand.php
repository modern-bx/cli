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

class AddCommand extends KernelCommand
{
    use IO;
    use RemotePhpTrait;
    use SectionFieldsValidationTrait;

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

    protected static $defaultName = 'iblock.section:add';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.iblock_section_add.description'))
            ->setHelp($this->trans('command.iblock_section_add.help'))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputArgument(
                    'fields',
                    InputArgument::OPTIONAL,
                    $this->trans('argument.iblock_section.add_fields')
                ),
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
        $fields = $this->getDecodedFields($input);

        if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new \RuntimeException('Не удалось подключить модуль iblock.');
        }

        /** @phpstan-ignore-next-line */
        $section = new \CIBlockSection();
        /** @phpstan-ignore-next-line */
        $id = $section->Add($fields);

        if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id) || (int) $id <= 0) {
            /** @phpstan-ignore-next-line */
            $lastError = is_string($section->LAST_ERROR ?? null) ? trim($section->LAST_ERROR) : '';
            throw new \Exception(
                $lastError !== '' ? $lastError : $this->trans('error.iblock_section.add_failed'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $this->printer->info((string) $id);
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        $id = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp(
                $remote,
                $this->remoteIBlockSectionPhpCodeBuilder->buildAdd($this->getDecodedFields($input))
            ),
            'Не удалось добавить раздел инфоблока удаленного проекта.'
        );

        if (!is_scalar($id) && $id !== null) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный ID раздела инфоблока.');
        }

        $this->printer->info((string) $id);
    }

    /** @return array<string, mixed> */
    private function getDecodedFields(InputInterface $input): array
    {
        $fields = $input->getArgument('fields');

        $decodedFields = $this->decodeFields($this->readFieldsArgumentOrStdin($fields));
        $this->validateFields($decodedFields);

        return $decodedFields;
    }
}
