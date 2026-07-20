<?php

declare(strict_types=1);

namespace ModernBx\Cli\App;

use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\CommandFinder;
use ModernBx\Cli\App\Service\ConfigurationService;
use ModernBx\Cli\App\Service\DynamicCommandLoader;
use ModernBx\Cli\App\Service\RuntimeInfo;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\ProjectNameGenerator;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemoteCachePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteDbPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteFileApplyPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteFilePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteIBlockElementPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteIBlockSectionPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteModulePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteOptionPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteSettingPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteSitePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteSqlPhpCodeBuilder;
use ModernBx\Cli\App\Service\Db\MySqlDumper;
use ModernBx\Cli\App\Service\Db\MySqlExecutor;
use ModernBx\Cli\App\Service\Db\PgSqlDumper;
use ModernBx\Cli\App\Service\Db\PgSqlExecutor;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DefaultContainerBuilder
{
    /**
     * @var ContainerBuilder
     */
    protected ContainerBuilder $containerBuilder;

    /**
     * @return ContainerInterface
     * @throws \Exception
     */
    public function getContainer(): ContainerInterface
    {
        if (isset($this->containerBuilder)) {
            return $this->containerBuilder;
        }

        $this->containerBuilder = new ContainerBuilder();

        $this->registerTranslator($this->containerBuilder);

        $this->containerBuilder
            ->autowire(DynamicCommandLoader::class)
            ->addArgument($this->containerBuilder)
            ->setPublic(true);

        $this->containerBuilder
            ->autowire(Application::class, Application::class)
            ->addArgument("@build-name@")
            ->addArgument("@build-version@ [@build-bundles@]")
            ->addMethodCall("setCommandLoader", [new Reference(DynamicCommandLoader::class)])
            ->setPublic(true);

        $this->containerBuilder
            ->autowire(ClassAliasLoader::class, ClassAliasLoader::class);

        $this->containerBuilder
            ->autowire(ConfigurationService::class, ConfigurationService::class);

        $this->containerBuilder
            ->autowire(MySqlDumper::class, MySqlDumper::class);

        $this->containerBuilder
            ->autowire(MySqlExecutor::class, MySqlExecutor::class);

        $this->containerBuilder
            ->autowire(PgSqlExecutor::class, PgSqlExecutor::class);

        $this->containerBuilder
            ->autowire(PgSqlDumper::class, PgSqlDumper::class);

        $this->containerBuilder
            ->autowire(ProjectNameGenerator::class, ProjectNameGenerator::class);

        $this->containerBuilder
            ->autowire(ProjectRegistry::class, ProjectRegistry::class);

        $this->containerBuilder
            ->autowire(BitrixAdminClient::class, BitrixAdminClient::class);

        $this->containerBuilder
            ->autowire(RemoteProjectConfigManager::class, RemoteProjectConfigManager::class);

        $this->containerBuilder
            ->autowire(RemoteCachePhpCodeBuilder::class, RemoteCachePhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteSqlPhpCodeBuilder::class, RemoteSqlPhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteDbPhpCodeBuilder::class, RemoteDbPhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteFileApplyPhpCodeBuilder::class, RemoteFileApplyPhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteFilePhpCodeBuilder::class, RemoteFilePhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteSettingPhpCodeBuilder::class, RemoteSettingPhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteOptionPhpCodeBuilder::class, RemoteOptionPhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteModulePhpCodeBuilder::class, RemoteModulePhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteIBlockElementPhpCodeBuilder::class, RemoteIBlockElementPhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteIBlockSectionPhpCodeBuilder::class, RemoteIBlockSectionPhpCodeBuilder::class);

        $this->containerBuilder
            ->autowire(RemoteSitePhpCodeBuilder::class, RemoteSitePhpCodeBuilder::class);

        $runtimeInfo = $this->containerBuilder
            ->autowire(RuntimeInfo::class, RuntimeInfo::class);

        $finder = $this->containerBuilder
            ->autowire(CommandFinder::class, CommandFinder::class)
            ->addArgument("ModernBx/Cli/App/Console/Command/")
            ->addArgument("Command");

        $this->registerCommands($this->containerBuilder, $finder, $runtimeInfo);

        $this->containerBuilder->compile();

        return $this->containerBuilder;
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    protected function registerTranslator(ContainerBuilder $containerBuilder): void
    {
        $locale = $_SERVER["APP_LOCALE"] ?? getenv("APP_LOCALE") ?: "ru";

        $containerBuilder
            ->autowire(PhpFileLoader::class, PhpFileLoader::class);

        $containerBuilder
            ->autowire(Translator::class, Translator::class)
            ->addArgument($locale)
            ->addMethodCall("addLoader", ["php", new Reference(PhpFileLoader::class)])
            ->addMethodCall("addResource", ["php", $_SERVER["DOCUMENT_ROOT"] . "/lang/ru/messages.php", "ru"])
            ->addMethodCall("addResource", ["php", $_SERVER["DOCUMENT_ROOT"] . "/lang/en/messages.php", "en"])
            ->addMethodCall("setFallbackLocales", [["ru", "en"]]);

        $containerBuilder->setAlias(TranslatorInterface::class, Translator::class);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     * @param Definition $finder
     * @param Definition $runtimeInfo
     */
    protected function registerCommands(
        ContainerBuilder $containerBuilder,
        Definition $finder,
        Definition $runtimeInfo
    ): void {
        $klass = $runtimeInfo->getClass();
        $runtimeInfo = new $klass(...$runtimeInfo->getArguments());

        $klass = $finder->getClass();
        $finder = new $klass(...array_merge($finder->getArguments(), [$runtimeInfo]));

        foreach ($finder->findCommands() as $id => $klass) {
            $containerBuilder
                ->autowire($id, $klass)
                ->setPublic(true);
        }
    }
}
