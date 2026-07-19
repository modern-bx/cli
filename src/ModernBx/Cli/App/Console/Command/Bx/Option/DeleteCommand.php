<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Option;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteOptionPhpCodeBuilder;
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

    private RemoteOptionPhpCodeBuilder $remoteOptionPhpCodeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteOptionPhpCodeBuilder $remoteOptionPhpCodeBuilder
    ) {
        parent::__construct($aliasLoader);

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteOptionPhpCodeBuilder = $remoteOptionPhpCodeBuilder;
    }

    /**
     * @var string
     */
    protected static $defaultName = 'option:delete';

    protected function configure(): void
    {
        $this
            ->setDescription('Удаляет опцию Bitrix')
            ->setHelp('Удаляет опцию Bitrix. Формат имени: module.option[.lid].')
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'remote',
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Кодовое имя удаленного проекта',
                    ),
                    new InputOption(
                        'local',
                        null,
                        InputOption::VALUE_NONE,
                        'Отключить неявный remote текущей сессии',
                    ),
                    new InputArgument(
                        'option',
                        InputArgument::REQUIRED,
                        $this->trans("argument.option.name"),
                    ),
                ])
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
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

        /** @var string $option */
        $option = $input->getArgument("option");
        [$moduleName, $optionName, $siteId] = $this->parseOptionName($option);

        $defaultValue = "\0BX_CLI_OPTION_NOT_FOUND\0";

        /** @noinspection PhpUndefinedClassInspection */
        /** @noinspection PhpUndefinedNamespaceInspection */
        /** @var string $optionValue */
        /** @phpstan-ignore-next-line */
        $optionValue = \Bitrix\Main\Config\Option::get(
            $moduleName,
            $optionName,
            $defaultValue,
            $siteId !== null ? $siteId : false,
        );

        if ($optionValue === $defaultValue) {
            $this->printer->put($this->getOptionNotFoundMessage($option), "comment");
            return;
        }

        /** @noinspection PhpUndefinedClassInspection */
        /** @noinspection PhpUndefinedNamespaceInspection */
        /** @phpstan-ignore-next-line */
        \Bitrix\Main\Config\Option::delete(
            $moduleName,
            [
                'name' => $optionName,
                'site_id' => $siteId ?? '',
            ],
        );
    }

    /**
     * @throws \Exception
     */
    protected function executeRemote(InputInterface $input, string $remote): void
    {
        /** @var string $option */
        $option = $input->getArgument("option");

        $result = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp(
                $remote,
                $this->remoteOptionPhpCodeBuilder->buildDelete($option)
            ),
            'Не удалось удалить опцию удаленного проекта.',
        );

        if (is_array($result) && ($result['warning'] ?? null) === 'OPTION_NOT_FOUND') {
            $this->printer->put($this->getOptionNotFoundMessage($option), "comment");
        }
    }

    private function getOptionNotFoundMessage(string $option): string
    {
        return sprintf('Опция %s не найдена в БД.', $option);
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function parseOptionName(string $option): array
    {
        $parts = explode(".", $option);

        if (count($parts) < 2 || count($parts) > 3 || in_array('', $parts, true)) {
            throw new \InvalidArgumentException('Имя опции должно быть в формате module.option[.lid].');
        }

        return [$parts[0], $parts[1], $parts[2] ?? null];
    }
}
