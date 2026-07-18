<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\to_json;

class SiteGetCommand extends KernelCommand
{
    use IO;
    use RemotePhpTrait;

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient
    ) {
        parent::__construct($aliasLoader);

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
    }

    /**
     * @var string
     */
    protected static $defaultName = 'site:get';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.site_get.description"))
            ->setHelp($this->trans("command.site_get.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'remote',
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Кодовое имя удаленного проекта',
                    ),
                    new InputOption(
                        'select',
                        null,
                        InputOption::VALUE_REQUIRED,
                        $this->trans("option.site.select"),
                    ),
                    new InputOption(
                        'pretty',
                        null,
                        InputOption::VALUE_NONE,
                        $this->trans("option.json.pretty_bx"),
                    ),
                    new InputArgument(
                        'id',
                        InputArgument::REQUIRED,
                        $this->trans("argument.site.id"),
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
        $this->executeLocal($input);
    }

    protected function executeLocal(InputInterface $input): void
    {
        $siteId = $input->getArgument("id");

        if (!is_string($siteId)) {
            throw new \Exception($this->trans("error.site.id_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $query = [
            "filter" => ["=LID" => $siteId],
            "limit" => 1,
        ];
        $select = $input->getOption("select");

        if ($select !== null) {
            if (!is_string($select)) {
                throw new \Exception(
                    $this->trans("error.option_json_string", ["%option%" => "select"]),
                    static::CODE_INVALID_OPTION_VALUE
                );
            }

            $query["select"] = $this->decodeSelectOption($select);
        }

        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        /** @noinspection PhpUndefinedClassInspection */
        /** @phpstan-ignore-next-line */
        $site = \Bitrix\Main\SiteTable::getList($query)->fetch();

        if (!$site) {
            return;
        }

        $this->printer->info((string) to_json($site, $flags));
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        $siteId = $input->getArgument("id");

        if (!is_string($siteId)) {
            throw new \Exception($this->trans("error.site.id_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $query = [
            "filter" => ["=LID" => $siteId],
            "limit" => 1,
        ];
        $select = $input->getOption("select");

        if ($select !== null) {
            if (!is_string($select)) {
                throw new \Exception(
                    $this->trans("error.option_json_string", ["%option%" => "select"]),
                    static::CODE_INVALID_OPTION_VALUE
                );
            }

            $query["select"] = $this->decodeSelectOption($select);
        }

        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $line = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($remote, $this->buildRemoteSiteCode('get', ['query' => $query, 'flags' => $flags])),
            'Не удалось получить сайт удаленного проекта.',
        );

        if ($line === null) {
            return;
        }

        $this->printer->info(is_scalar($line) ? (string) $line : '');
    }

    /**
     * @param string $value
     * @return mixed
     * @throws \Exception
     */
    private function decodeSelectOption(string $value): mixed
    {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                $this->trans("error.option_invalid_json", [
                    "%option%" => "select",
                    "%message%" => json_last_error_msg(),
                ]),
                static::CODE_INVALID_OPTION_VALUE
            );
        }

        return $decoded;
    }
}
