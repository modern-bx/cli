<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

use function ModernBx\CommonFunctions\to_json;

class ShowConfigCommand extends AppCommand
{
    protected static $defaultName = 'remote:show-config';

    protected ProjectRegistry $projectRegistry;

    public function __construct(ProjectRegistry $projectRegistry, ?TranslatorInterface $translator = null)
    {
        $this->projectRegistry = $projectRegistry;

        parent::__construct($translator);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Выводит конфигурацию зарегистрированного удаленного проекта')
            ->setDefinition(
                new InputDefinition([
                    new InputArgument('codename', InputArgument::REQUIRED, 'Кодовое имя проекта'),
                    new InputOption(
                        'format',
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Формат вывода: yaml или json',
                        'yaml',
                    ),
                ]),
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        /** @var string $codename */
        $codename = $input->getArgument('codename');
        $format = $this->normalizeFormat($input->getOption('format'));
        $file = $this->projectRegistry->getConfigFile($codename);

        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('Проект не зарегистрирован: %s', $codename), static::CODE_IO_ERROR);
        }

        if ($format === 'json') {
            $config = Yaml::parseFile($file);

            if (!is_array($config)) {
                throw new \RuntimeException(
                    sprintf('Некорректная конфигурация проекта: %s', $codename),
                    static::CODE_INVALID_FILE_CONTENT,
                );
            }

            $json = to_json($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            if (!is_string($json)) {
                throw new \RuntimeException(
                    sprintf('Не удалось преобразовать конфигурацию проекта в JSON: %s', $codename),
                    static::CODE_INVALID_FILE_CONTENT,
                );
            }

            $this->printer->info($json);
            return;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            throw new \RuntimeException(
                sprintf('Не удалось прочитать конфигурацию проекта: %s', $codename),
                static::CODE_IO_ERROR,
            );
        }

        $this->printer->info(rtrim($content, "\r\n"));
    }

    private function normalizeFormat(mixed $format): string
    {
        if (!is_string($format)) {
            throw new \RuntimeException('Формат вывода должен быть строкой.', static::CODE_INVALID_OPTION_VALUE);
        }

        $format = strtolower(trim($format));

        if ($format === 'yml' || $format === 'yam') {
            return 'yaml';
        }

        if ($format === 'yaml' || $format === 'json') {
            return $format;
        }

        throw new \RuntimeException('Формат вывода должен быть yaml или json.', static::CODE_INVALID_OPTION_VALUE);
    }
}
