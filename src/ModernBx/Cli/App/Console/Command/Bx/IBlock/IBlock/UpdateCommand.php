<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\IBlock\IBlock;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteIBlockPhpCodeBuilder;
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
    use IBlockFieldsValidationTrait;

    private RemoteIBlockPhpCodeBuilder $remoteIBlockPhpCodeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteIBlockPhpCodeBuilder $remoteIBlockPhpCodeBuilder
    ) {
        parent::__construct($aliasLoader);

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteIBlockPhpCodeBuilder = $remoteIBlockPhpCodeBuilder;
    }

    protected static $defaultName = 'iblock:update';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.iblock_update.description'))
            ->setHelp($this->trans('command.iblock_update.help'))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputArgument('ID', InputArgument::REQUIRED, $this->trans('argument.iblock.id')),
                new InputArgument(
                    'fields',
                    InputArgument::OPTIONAL,
                    $this->trans('argument.iblock.update_fields')
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
        [$id, $fields] = $this->getIBlockUpdateArguments($input);

        if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new \RuntimeException('Не удалось подключить модуль iblock.');
        }

        /** @phpstan-ignore-next-line */
        $iblock = new \CIBlock();
        /** @phpstan-ignore-next-line */
        if (!$iblock->Update($id, $fields)) {
            /** @phpstan-ignore-next-line */
            $lastError = is_string($iblock->LAST_ERROR ?? null) ? trim($iblock->LAST_ERROR) : '';
            throw new \Exception(
                $lastError !== '' ? $lastError : $this->trans('error.iblock.update_failed'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        [$id, $fields] = $this->getIBlockUpdateArguments($input);

        $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($remote, $this->remoteIBlockPhpCodeBuilder->buildUpdate($id, $fields)),
            'Не удалось обновить инфоблок удаленного проекта.'
        );
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function getIBlockUpdateArguments(InputInterface $input): array
    {
        $id = $input->getArgument('ID');
        $fields = $input->getArgument('fields');

        if (!is_string($id) || !ctype_digit($id) || (int) $id <= 0) {
            throw new \Exception(
                $this->trans('error.iblock.id_positive'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $decodedFields = $this->decodeFields($this->readFieldsArgumentOrStdin($fields));
        $this->validateFields($decodedFields);

        return [(int) $id, $decodedFields];
    }
}
