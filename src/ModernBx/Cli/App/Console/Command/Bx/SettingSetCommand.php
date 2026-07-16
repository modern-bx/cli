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

class SettingSetCommand extends BxCommand
{
    use SettingFile;
    /**
     * @var string
     */
    protected static $defaultName = 'setting:set';

    protected function configure(): void
    {
        $this
            ->setDescription("Set Bitrix settings value")
            ->setHelp("Set a value in .settings.php or .settings_extra.php. Skip the root value segment in path")
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'extra',
                        null,
                        InputOption::VALUE_NONE,
                        "Write .settings_extra.php instead of .settings.php",
                    ),
                    new InputArgument(
                        'path',
                        InputArgument::REQUIRED,
                        "Dot-separated settings path without the root value segment",
                    ),
                    new InputArgument(
                        'value',
                        InputArgument::REQUIRED,
                        "New setting value. JSON scalars and structures are decoded; other values are saved as strings",
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
        $value = $input->getArgument("value");

        if (!is_string($path)) {
            throw new \Exception("Setting path must be a string.", static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if (!is_string($value)) {
            throw new \Exception("Setting value must be a string.", static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $value = $this->decodeValue($value);

        $this->setSettingValue($settings, $this->getPathSegments($path), $value);
        $this->saveSettings($settings, $file);
        $this->printer->info("Settings value has been updated.");
    }

    /**
     * @param string $value
     * @return mixed
     */
    private function decodeValue(string $value): mixed
    {
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }
}
