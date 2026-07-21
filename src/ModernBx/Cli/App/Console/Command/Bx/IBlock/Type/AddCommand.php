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

class AddCommand extends KernelCommand
{
    use IO;
    use RemotePhpTrait;
    use IBlockTypeFieldsValidationTrait;

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

    protected static $defaultName = 'iblock.type:add';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.iblock_type_add.description'))
            ->setHelp($this->trans('command.iblock_type_add.help'))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputArgument('fields', InputArgument::OPTIONAL, $this->trans('argument.iblock_type.add_fields')),
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
        $iblockType = new \CIBlockType();
        $id = $fields['ID'] ?? null;
        if (!is_string($id) || trim($id) === '') {
            throw new \Exception(
                $this->trans('error.iblock_type.id_required'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        /** @phpstan-ignore-next-line */
        $added = $iblockType->Add($fields);

        if (!$added) {
            /** @phpstan-ignore-next-line */
            $lastError = is_string($iblockType->LAST_ERROR ?? null) ? trim($iblockType->LAST_ERROR) : '';
            throw new \Exception(
                $lastError !== '' ? $lastError : $this->trans('error.iblock_type.add_failed'),
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
                $this->remoteIBlockTypePhpCodeBuilder->buildAdd($this->getDecodedFields($input))
            ),
            'Не удалось добавить тип инфоблока удаленного проекта.'
        );

        if (!is_scalar($id) && $id !== null) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный ID типа инфоблока.');
        }

        $this->printer->info((string) $id);
    }

    /** @return array<string, mixed> */
    private function getDecodedFields(InputInterface $input): array
    {
        $decodedFields = $this->decodeFields($this->readFieldsArgumentOrStdin($input->getArgument('fields')));
        $this->validateFields($decodedFields);

        return $decodedFields;
    }
}
