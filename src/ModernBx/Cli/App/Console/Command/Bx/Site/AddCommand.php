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

class AddCommand extends KernelCommand
{
    use IO;
    use RemotePhpTrait;
    use SiteFieldsValidationTrait;

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

    protected static $defaultName = 'site:add';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.site_add.description"))
            ->setHelp($this->trans("command.site_add.help"))
            ->setDefinition(new InputDefinition([
                new InputOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта'),
                new InputOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии'),
                new InputArgument('fields', InputArgument::REQUIRED, $this->trans("argument.site.add_fields")),
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

        /** @noinspection PhpUndefinedClassInspection */
        /** @phpstan-ignore-next-line */
        $result = \Bitrix\Main\SiteTable::add($fields);

        if (!$result->isSuccess()) {
            throw new \Exception(implode(PHP_EOL, $result->getErrorMessages()), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $this->printer->info((string) $result->getId());
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        $id = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp(
                $remote,
                $this->remoteSitePhpCodeBuilder->buildAdd($this->getDecodedFields($input)),
            ),
            'Не удалось добавить сайт удаленного проекта.',
        );

        if (!is_scalar($id) && $id !== null) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный ID сайта.');
        }

        $this->printer->info((string) $id);
    }

    /** @return array<string, mixed> */
    private function getDecodedFields(InputInterface $input): array
    {
        $fields = $input->getArgument("fields");

        if (!is_string($fields)) {
            throw new \Exception($this->trans("error.site.update_json_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $decodedFields = $this->decodeFields($fields);
        $this->validateFields($decodedFields);

        return $decodedFields;
    }
}
