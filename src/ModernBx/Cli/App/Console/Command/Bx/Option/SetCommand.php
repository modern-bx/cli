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

class SetCommand extends KernelCommand
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
    protected static $defaultName = 'option:set';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.option_set.description"))
            ->setHelp($this->trans("command.option_set.help"))
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
                    new InputArgument(
                        'value',
                        InputArgument::REQUIRED,
                        $this->trans("argument.option.value"),
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
        /** @var string $value */
        $value = $input->getArgument("value");
        [$moduleName, $optionName, $siteId] = $this->parseOptionName($option);

        /** @noinspection PhpUndefinedClassInspection */
        /** @noinspection PhpUndefinedNamespaceInspection */
        /** @var string $optionValue */
        /** @phpstan-ignore-next-line */
        \Bitrix\Main\Config\Option::set(
            $moduleName,
            $optionName,
            $value,
            $siteId ?? "",
        );
    }

    /**
     * @throws \Exception
     */
    protected function executeRemote(InputInterface $input, string $remote): void
    {
        /** @var string $option */
        $option = $input->getArgument("option");
        /** @var string $value */
        $value = $input->getArgument("value");

        $this->decodeRemoteJsonResult(
            $this->executeRemotePhp(
                $remote,
                $this->remoteOptionPhpCodeBuilder->buildSet($option, $value)
            ),
            'Не удалось обновить опцию удаленного проекта.',
        );
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
