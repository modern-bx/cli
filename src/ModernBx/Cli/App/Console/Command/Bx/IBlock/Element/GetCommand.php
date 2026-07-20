<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\IBlock\Element;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteIBlockElementPhpCodeBuilder;
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

    private RemoteIBlockElementPhpCodeBuilder $remoteIBlockElementPhpCodeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteIBlockElementPhpCodeBuilder $remoteIBlockElementPhpCodeBuilder
    ) {
        parent::__construct($aliasLoader);

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteIBlockElementPhpCodeBuilder = $remoteIBlockElementPhpCodeBuilder;
    }

    protected static $defaultName = 'iblock.element:get';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.iblock_element_get.description'))
            ->setHelp($this->trans('command.iblock_element_get.help'))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputOption('pretty', null, InputOption::VALUE_NONE, $this->trans('option.json.pretty_bx')),
                new InputArgument('ID', InputArgument::REQUIRED, $this->trans('argument.iblock_element.id')),
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
        $id = $this->getElementId($input);
        $flags = $this->getJsonFlags($input);

        if (!class_exists('\Bitrix\Main\Loader') || !\Bitrix\Main\Loader::includeModule('iblock')) {
            throw new \RuntimeException('Не удалось подключить модуль iblock.');
        }

        if (!class_exists('CIBlockElement')) {
            throw new \RuntimeException('Класс CIBlockElement недоступен.');
        }

        $result = \CIBlockElement::GetList([], ['ID' => $id], false, ['nTopCount' => 1], ['*']);
        $element = $result->GetNextElement();

        if (!$element) {
            throw new \Exception(
                $this->trans('error.iblock_element.not_found', ['%id%' => (string) $id]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $this->printer->info((string) to_json($this->normalizeTildeFields($element->GetFields()), $flags));
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        $id = $this->getElementId($input);
        $flags = $this->getJsonFlags($input);

        $line = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($remote, $this->remoteIBlockElementPhpCodeBuilder->buildGet($id, $flags)),
            'Не удалось получить элемент инфоблока удаленного проекта.'
        );

        $this->printer->info(is_scalar($line) ? (string) $line : '');
    }

    private function getElementId(InputInterface $input): int
    {
        $id = $input->getArgument('ID');

        if (!is_string($id) || !ctype_digit($id) || (int) $id <= 0) {
            throw new \Exception(
                $this->trans('error.iblock_element.id_positive'),
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
