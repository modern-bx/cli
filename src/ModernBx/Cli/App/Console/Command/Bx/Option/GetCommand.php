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

use function ModernBx\CommonFunctions\to_json;

class GetCommand extends KernelCommand
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
    protected static $defaultName = 'option:get';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.option_get.description"))
            ->setHelp($this->trans("command.option_get.help"))
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
                    new InputOption(
                        'unserialize',
                        'u',
                        InputOption::VALUE_NONE,
                        $this->trans("option.option.unserialize"),
                    ),
                    new InputArgument(
                        'option',
                        InputArgument::IS_ARRAY,
                        $this->trans("argument.option.list"),
                    ),
                ]),
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

        /** @var array<string> $optionList */
        $optionList = $input->getArgument("option");

        foreach ($optionList as $option) {
            [$moduleName, $optionName, $siteId] = explode(".", $option);

            /** @noinspection PhpUndefinedClassInspection */
            /** @noinspection PhpUndefinedNamespaceInspection */
            /** @var string $optionValue */
            /** @phpstan-ignore-next-line */
            $optionValue = \Bitrix\Main\Config\Option::get(
                $moduleName,
                $optionName,
                "",
                $siteId ?: false,
            );

            if ($input->getOption("unserialize")) {
                $unserializedValue = @unserialize($optionValue);
            }

            $this->printer->info((string) to_json($unserializedValue ?? $optionValue));
        }
    }

    /**
     * @throws \Exception
     */
    protected function executeRemote(InputInterface $input, string $remote): void
    {
        /** @var array<string> $optionList */
        $optionList = $input->getArgument("option");

        $lines = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp(
                $remote,
                $this->remoteOptionPhpCodeBuilder->buildGet($optionList, (bool) $input->getOption("unserialize"))
            ),
            'Не удалось получить опцию удаленного проекта.',
        );

        if (!is_array($lines)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный результат получения опций.');
        }

        foreach ($lines as $line) {
            $this->printer->info(is_scalar($line) ? (string) $line : '');
        }
    }
}
