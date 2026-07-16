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
            ->setDescription("Get Bitrix settings value")
            ->setHelp("Print a value from .settings.php or .settings_extra.php. Skip the root value segment in path")
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'extra',
                        null,
                        InputOption::VALUE_NONE,
                        "Read .settings_extra.php instead of .settings.php",
                    ),
                    new InputOption(
                        'pretty',
                        null,
                        InputOption::VALUE_NONE,
                        "Pretty-print JSON output",
                    ),
                    new InputArgument(
                        'path',
                        InputArgument::REQUIRED,
                        "Dot-separated settings path without the root value segment",
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
            throw new \Exception("Setting path must be a string.", static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $value = $this->getSettingValue($settings, $this->getPathSegments($path));
        $flags = JSON_UNESCAPED_UNICODE;

        if ($input->getOption("pretty")) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->printer->info((string) to_json($value, $flags));
    }
}
