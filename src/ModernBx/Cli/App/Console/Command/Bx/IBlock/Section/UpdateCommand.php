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

class UpdateCommand extends KernelCommand
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

    protected static $defaultName = 'iblock.section:update';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.iblock_section_update.description'))
            ->setHelp($this->trans('command.iblock_section_update.help'))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputArgument('ID', InputArgument::REQUIRED, $this->trans('argument.iblock_section.id')),
                new InputArgument(
                    'fields',
                    InputArgument::OPTIONAL,
                    $this->trans('argument.iblock_section.update_fields')
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
        [$id, $fields] = $this->getSectionUpdateArguments($input);

        if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new \RuntimeException('Не удалось подключить модуль iblock.');
        }

        /** @phpstan-ignore-next-line */
        $section = new \CIBlockSection();
        /** @phpstan-ignore-next-line */
        if (!$section->Update($id, $fields)) {
            /** @phpstan-ignore-next-line */
            $lastError = is_string($section->LAST_ERROR ?? null) ? trim($section->LAST_ERROR) : '';
            throw new \Exception(
                $lastError !== '' ? $lastError : $this->trans('error.iblock_section.update_failed'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        [$id, $fields] = $this->getSectionUpdateArguments($input);

        $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($remote, $this->remoteIBlockSectionPhpCodeBuilder->buildUpdate($id, $fields)),
            'Не удалось обновить раздел инфоблока удаленного проекта.'
        );
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function getSectionUpdateArguments(InputInterface $input): array
    {
        $id = $input->getArgument('ID');
        $fields = $input->getArgument('fields');

        if (!is_string($id) || !ctype_digit($id) || (int) $id <= 0) {
            throw new \Exception(
                $this->trans('error.iblock_section.id_positive'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $decodedFields = $this->decodeFields($this->readFieldsArgumentOrStdin($fields));
        $this->validateFields($decodedFields);

        return [(int) $id, $decodedFields];
    }
}
