<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Console\Mixin\Bx\SettingFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\to_json;

class SettingGetCommand extends BxCommand
{
    use SettingFile;
    /**
     * @var string
     */
    protected static $defaultName = 'setting:get';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.setting_get.description"))
            ->setHelp($this->trans("command.setting_get.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'extra',
                        null,
                        InputOption::VALUE_NONE,
                        $this->trans("option.setting.extra_read"),
                    ),
                    new InputOption(
                        'pretty',
                        null,
                        InputOption::VALUE_NONE,
                        $this->trans("option.json.pretty_bx"),
                    ),
                    new InputArgument(
                        'path',
                        InputArgument::REQUIRED,
                        $this->trans("argument.setting.path"),
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

        $file = $this->getSettingsFile((bool) $input->getOption("extra"));
        $settings = $this->loadSettings($file);
        $path = $input->getArgument("path");

        if (!is_string($path)) {
            throw new \Exception($this->trans("error.setting.path_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $value = $this->getSettingValue($settings, $this->getPathSegments($path));
        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->printer->info((string) to_json($value, $flags));
    }
}
