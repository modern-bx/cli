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

class DeleteCommand extends KernelCommand
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

    protected static $defaultName = 'iblock.section:delete';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.iblock_section_delete.description'))
            ->setHelp($this->trans('command.iblock_section_delete.help'))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
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

        if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new \RuntimeException('Не удалось подключить модуль iblock.');
        }

        /** @phpstan-ignore-next-line */
        if (!\CIBlockSection::Delete($id)) {
            throw new \Exception($this->getLastApplicationError(), static::CODE_INVALID_ARGUMENT_VALUE);
        }
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        $this->decodeRemoteJsonResult(
            $this->executeRemotePhp(
                $remote,
                $this->remoteIBlockSectionPhpCodeBuilder->buildDelete($this->getSectionId($input))
            ),
            'Не удалось удалить раздел инфоблока удаленного проекта.'
        );
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

    private function getLastApplicationError(): string
    {
        global $APPLICATION;

        if (is_object($APPLICATION) && method_exists($APPLICATION, 'GetException')) {
            $exception = $APPLICATION->GetException();
            if (is_object($exception) && method_exists($exception, 'GetString')) {
                $message = $exception->GetString();
                if (is_string($message) && trim($message) !== '') {
                    return trim($message);
                }
            }
        }

        return $this->trans('error.iblock_section.delete_failed');
    }
}
