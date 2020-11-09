<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Console\Mixin\ComposerAware;
use ModernBx\Cli\App\Service\ClassAliasLoader;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KernelCommand extends BxCommand
{
    use ComposerAware;

    /**
     * @var ClassAliasLoader
     */
    protected ClassAliasLoader $aliasLoader;

    /**
     * @param ClassAliasLoader $aliasLoader
     */
    public function __construct(ClassAliasLoader $aliasLoader)
    {
        parent::__construct();

        $this->aliasLoader = $aliasLoader;
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

        $prologue = $this->bxRoot->toString() . "modules/main/include/prolog_before.php";

        set_time_limit(0);
        error_reporting(E_ERROR);

        if (!date_default_timezone_get()) {
            date_default_timezone_set("Europe/Moscow");
        }

        $this->concealAutoloadFiles();

        $GLOBALS["DOCUMENT_ROOT"] = $_SERVER["DOCUMENT_ROOT"] = $this->getDocumentRoot()->toString();

        define('BX_CRONTAB', true);
        define('NO_AGENT_CHECK', true);
        define('NO_KEEP_STATISTIC', true);
        define('NOT_CHECK_PERMISSIONS', true);

        // phpcs:disable
        // Пролог должен быть загружен ВНЕ пространства имен команды
        eval("require_once '$prologue';");
        // phpcs:enable

        $this->aliasLoader->loadClassAliases();

        global $APPLICATION;

        $APPLICATION->RestartBuffer();

        while (ob_get_level()) {
            ob_end_flush();
        }
    }
}
