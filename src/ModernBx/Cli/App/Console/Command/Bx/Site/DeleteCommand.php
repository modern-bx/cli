<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Site;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteSitePhpCodeBuilder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCommand extends KernelCommand
{
    use IO;
    use RemotePhpTrait;

    private RemoteSitePhpCodeBuilder $remoteSitePhpCodeBuilder;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteSitePhpCodeBuilder $remoteSitePhpCodeBuilder
    ) {
        parent::__construct($aliasLoader);
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteSitePhpCodeBuilder = $remoteSitePhpCodeBuilder;
    }

    protected static $defaultName = 'site:delete';

    protected function configure(): void
    {
        $this->setDescription($this->trans("command.site_delete.description"))
            ->setHelp($this->trans("command.site_delete.help"))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputArgument('id', InputArgument::REQUIRED, $this->trans("argument.site.id")),
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
        $id = $this->getSiteId($input);

        /** @noinspection PhpUndefinedClassInspection */
        /** @phpstan-ignore-next-line */
        $result = \Bitrix\Main\SiteTable::delete($id);

        if (!$result->isSuccess()) {
            throw new \Exception(implode(PHP_EOL, $result->getErrorMessages()), static::CODE_INVALID_ARGUMENT_VALUE);
        }
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($remote, $this->remoteSitePhpCodeBuilder->buildDelete($this->getSiteId($input))),
            'Не удалось удалить сайт удаленного проекта.',
        );
    }

    private function getSiteId(InputInterface $input): string
    {
        $id = $input->getArgument("id");

        if (!is_string($id)) {
            throw new \Exception($this->trans("error.site.id_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return $id;
    }
}
