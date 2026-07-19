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
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

use function ModernBx\CommonFunctions\to_json;

class ListCommand extends KernelCommand
{
    use IO;
    use RemotePhpTrait;

    private RemoteSitePhpCodeBuilder $remoteSitePhpCodeBuilder;

    private ?OutputInterface $output = null;

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

    /**
     * @var string
     */
    protected static $defaultName = 'site:list';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.site_list.description"))
            ->setHelp($this->trans("command.site_list.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'remote',
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Кодовое имя удаленного проекта',
                    ),
                    new InputOption(
                        'filter',
                        null,
                        InputOption::VALUE_REQUIRED,
                        $this->trans("option.site.filter"),
                    ),
                    new InputOption(
                        'order',
                        null,
                        InputOption::VALUE_REQUIRED,
                        $this->trans("option.site.order"),
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
                    new InputOption(
                        'short',
                        null,
                        InputOption::VALUE_OPTIONAL,
                        'Выводить сайты строками по шаблону (по умолчанию: [$LID] $NAME [$SERVER_NAME])',
                        false,
                    ),
                    new InputOption(
                        'format',
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Формат вывода: table или csv',
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
        $this->output = $output;
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
        $query = [];

        foreach (["filter", "order", "select"] as $option) {
            $value = $input->getOption($option);

            if ($value === null) {
                continue;
            }

            if (!is_string($value)) {
                throw new \Exception(
                    $this->trans("error.option_json_string", ["%option%" => $option]),
                    static::CODE_INVALID_OPTION_VALUE
                );
            }

            $query[$option] = $this->decodeJsonOption($option, $value);
        }

        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->validateOutputOptions($input);

        /** @noinspection PhpUndefinedClassInspection */
        /** @phpstan-ignore-next-line */
        $cursor = \Bitrix\Main\SiteTable::getList($query);

        $sites = [];
        while ($site = $cursor->fetch()) {
            $sites[] = $site;
        }

        $this->renderSites($input, $sites, $flags);
    }

    protected function executeRemote(InputInterface $input, string $remote): void
    {
        $query = [];

        foreach (["filter", "order", "select"] as $option) {
            $value = $input->getOption($option);

            if ($value === null) {
                continue;
            }

            if (!is_string($value)) {
                throw new \Exception(
                    $this->trans("error.option_json_string", ["%option%" => $option]),
                    static::CODE_INVALID_OPTION_VALUE
                );
            }

            $query[$option] = $this->decodeJsonOption($option, $value);
        }

        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->validateOutputOptions($input);

        $result = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($remote, $this->remoteSitePhpCodeBuilder->buildList($query, 0)),
            'Не удалось получить список сайтов удаленного проекта.',
        );

        if (!is_array($result)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный список сайтов.');
        }

        $sites = [];
        foreach ($result as $site) {
            if (!is_array($site)) {
                throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный сайт.');
            }
            $sites[] = $site;
        }

        $this->renderSites($input, $sites, $flags);
    }

    /** @param array<int, array<string, mixed>> $sites */
    private function renderSites(InputInterface $input, array $sites, int $jsonFlags): void
    {
        $short = $input->getOption('short');
        if ($short !== false) {
            $template = is_string($short) && $short !== '' ? $short : '[$LID] $NAME [$SERVER_NAME]';
            foreach ($sites as $site) {
                $this->printer->info($this->formatShortSite($site, $template));
            }
            return;
        }

        $format = $input->getOption('format');
        if ($format === 'table') {
            $this->renderTable($sites);
            return;
        }
        if ($format === 'csv') {
            $this->renderCsv($sites);
            return;
        }

        foreach ($sites as $site) {
            $this->printer->info((string) to_json($site, $jsonFlags));
        }
    }

    private function validateOutputOptions(InputInterface $input): void
    {
        if ($input->getOption('short') !== false && $input->getOption('format') !== null) {
            throw new \RuntimeException('Опции --short и --format несовместимы.', static::CODE_INVALID_OPTION_VALUE);
        }

        $format = $input->getOption('format');
        if ($format !== null && !in_array($format, ['table', 'csv'], true)) {
            throw new \RuntimeException(
                'Опция --format поддерживает только значения table или csv.',
                static::CODE_INVALID_OPTION_VALUE
            );
        }
    }

    /** @param array<string, mixed> $site */
    private function formatShortSite(array $site, string $template): string
    {
        return (string) preg_replace_callback(
            '/\$([A-Za-z_][A-Za-z0-9_]*)/',
            function (array $matches) use ($site): string {
                $key = $matches[1];
                $value = $site[$key] ?? '';
                return $this->stringifyValue($value);
            },
            $template
        );
    }

    /** @param array<int, array<string, mixed>> $sites */
    private function renderTable(array $sites): void
    {
        $headers = $this->collectHeaders($sites);
        $rows = [];
        foreach ($sites as $site) {
            $rows[] = array_map(
                fn (string $header): string => $this->stringifyValue($site[$header] ?? ''),
                $headers
            );
        }

        (new Table($this->getOutput()))->setHeaders($headers)->setRows($rows)->render();
    }

    /** @param array<int, array<string, mixed>> $sites */
    private function renderCsv(array $sites): void
    {
        $headers = $this->collectHeaders($sites);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось подготовить CSV-вывод.', static::CODE_IO_ERROR);
        }

        fputcsv($handle, $headers);
        foreach ($sites as $site) {
            fputcsv(
                $handle,
                array_map(fn (string $header): string => $this->stringifyValue($site[$header] ?? ''), $headers)
            );
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        $this->getOutput()->write($csv === false ? '' : $csv);
    }

    /**
     * @param array<int, array<string, mixed>> $sites
     * @return array<int, string>
     */
    private function collectHeaders(array $sites): array
    {
        $headers = [];
        foreach ($sites as $site) {
            foreach (array_keys($site) as $key) {
                if (!in_array((string) $key, $headers, true)) {
                    $headers[] = (string) $key;
                }
            }
        }
        return $headers;
    }

    private function getOutput(): OutputInterface
    {
        if ($this->output === null) {
            throw new \RuntimeException('Output is not initialized.');
        }
        return $this->output;
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = to_json($value);
        return is_string($json) ? $json : '';
    }


    /**
     * @param string $option
     * @param string $value
     * @return mixed
     * @throws \Exception
     */
    private function decodeJsonOption(string $option, string $value): mixed
    {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                $this->trans("error.option_invalid_json", [
                    "%option%" => $option,
                    "%message%" => json_last_error_msg(),
                ]),
                static::CODE_INVALID_OPTION_VALUE
            );
        }

        return $decoded;
    }
}
