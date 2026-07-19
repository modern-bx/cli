<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Module;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteModulePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends KernelCommand
{
    use RemotePhpTrait;

    private RemoteModulePhpCodeBuilder $remoteModulePhpCodeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteModulePhpCodeBuilder $remoteModulePhpCodeBuilder
    ) {
        parent::__construct($aliasLoader);
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteModulePhpCodeBuilder = $remoteModulePhpCodeBuilder;
    }

    /** @var string */
    protected static $defaultName = 'module:version';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.module_version.description"))
            ->setHelp($this->trans("command.module_version.help"))
            ->setDefinition(new InputDefinition([
            new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
            new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
            new InputArgument('module', InputArgument::IS_ARRAY, $this->trans("argument.module.list")),
        ]));
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        /** @var array<string> $moduleList */
        $moduleList = $input->getArgument("module");
        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $result = $this->decodeRemoteJsonResult(
                $this->executeRemotePhp($remote, $this->remoteModulePhpCodeBuilder->buildVersion($moduleList)),
                'Не удалось получить версии модулей удаленного проекта.'
            );
            if (!is_array($result)) {
                throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный список версий модулей.');
            }
            foreach ($result as $version) {
                $this->printer->info((string) $version);
            }
            return;
        }

        parent::executeInternal($input, $output);
        foreach ($moduleList as $moduleCode) {
            /** @var string $version */
            /** @phpstan-ignore-next-line */
            $version = \Bitrix\Main\ModuleManager::getVersion($moduleCode);
            $this->printer->info($version);
        }
    }
}
