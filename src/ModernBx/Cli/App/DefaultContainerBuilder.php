<?php

declare(strict_types=1);

namespace ModernBx\Cli\App;

use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\CommandFinder;
use ModernBx\Cli\App\Service\ConfigurationService;
use ModernBx\Cli\App\Service\DynamicCommandLoader;
use ModernBx\Cli\App\Service\RuntimeInfo;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

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
