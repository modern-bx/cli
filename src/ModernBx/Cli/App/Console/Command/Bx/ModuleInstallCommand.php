<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ModuleInstallCommand extends KernelCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'module:install';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.module_install.description"))
            ->setHelp($this->trans("command.module_install.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'module',
                        InputArgument::REQUIRED,
                        $this->trans("argument.module.code"),
                    ),
                ])
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

        $moduleCode = $input->getArgument("module");

        if (!is_string($moduleCode) || $moduleCode === '') {
            throw new \Exception(
                $this->trans("error.module.code_string"),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        /** @phpstan-ignore-next-line */
        if (\Bitrix\Main\ModuleManager::isModuleInstalled($moduleCode)) {
            $this->printer->put(
                $this->trans("warning.module.already_installed", ["%module%" => $moduleCode]),
                "comment"
            );

            return;
        }

        $modulePath = $this->getModulePath($moduleCode);

        if ($modulePath === null) {
            throw new \Exception(
                $this->trans("error.module.not_found", ["%module%" => $moduleCode]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $installFile = $modulePath . "/install/index.php";

        if (!is_file($installFile)) {
            throw new \Exception(
                $this->trans("error.module.install_file_not_found", ["%module%" => $moduleCode]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        require_once $installFile;

        $installerClass = str_replace('.', '_', $moduleCode);

        if (!class_exists($installerClass)) {
            throw new \Exception(
                $this->trans("error.module.install_class_not_found", ["%class%" => $installerClass]),
                static::CODE_INVALID_FILE_CONTENT
            );
        }

        $installer = new $installerClass();

        if (!method_exists($installer, 'DoInstall')) {
            throw new \Exception(
                $this->trans("error.module.install_method_not_found", ["%class%" => $installerClass]),
                static::CODE_INVALID_FILE_CONTENT
            );
        }

        $installer->DoInstall();
        $this->printer->info($this->trans("message.module.installed", ["%module%" => $moduleCode]));
    }

    /**
     * @param string $moduleCode
     * @return string|null
     */
    protected function getModulePath(string $moduleCode): ?string
    {
        $paths = [
            $this->bxRoot->pushPathSegment("modules")->pushPathSegment($moduleCode)->toString(),
            $this->documentRoot
                ->pushPathSegment("local")
                ->pushPathSegment("modules")
                ->pushPathSegment($moduleCode)
                ->toString(),
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }
}
