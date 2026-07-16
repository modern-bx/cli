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
            ->setDescription($this->trans("command.setting_set.description"))
            ->setHelp($this->trans("command.setting_set.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'extra',
                        null,
                        InputOption::VALUE_NONE,
                        $this->trans("option.setting.extra_write"),
                    ),
                    new InputArgument(
                        'path',
                        InputArgument::REQUIRED,
                        $this->trans("argument.setting.path"),
                    ),
                    new InputArgument(
                        'value',
                        InputArgument::REQUIRED,
                        $this->trans("argument.setting.value"),
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
            throw new \Exception($this->trans("error.setting.path_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if (!is_string($value)) {
            throw new \Exception($this->trans("error.setting.value_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $value = $this->decodeValue($value);

        $this->setSettingValue($settings, $this->getPathSegments($path), $value);
        $this->saveSettings($settings, $file);
        $this->printer->info($this->trans("message.setting.updated"));
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
