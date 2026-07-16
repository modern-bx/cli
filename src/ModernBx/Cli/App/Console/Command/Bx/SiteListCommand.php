<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\Common\IO;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\to_json;

class SiteListCommand extends KernelCommand
{
    use IO;

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
        parent::executeInternal($input, $output);

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

        /** @noinspection PhpUndefinedClassInspection */
        /** @phpstan-ignore-next-line */
        $cursor = \Bitrix\Main\SiteTable::getList($query);

        while ($site = $cursor->fetch()) {
            $this->printer->info((string) to_json($site, $flags));
        }
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
