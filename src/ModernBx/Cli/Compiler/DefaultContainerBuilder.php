<?php

declare(strict_types=1);

namespace ModernBx\Cli\Compiler;

use ModernBx\Cli\Compiler\Console\Command\PharCleanCommand;
use ModernBx\Cli\Compiler\Console\Command\PharConfigureCommand;
use ModernBx\Cli\Compiler\Service\NamespaceFinder;

use Psr\Container\ContainerInterface;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

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
            ->autowire(Application::class, Application::class)
            ->addArgument("@compiler-name@")
            ->addMethodCall("setCommandLoader", [new ContainerCommandLoader($this->containerBuilder, [
                "phar:configure" => PharConfigureCommand::class,
                "phar:clean" => PharCleanCommand::class,
            ])])
            ->setPublic(true);

        $this->containerBuilder
            ->autowire(NamespaceFinder::class, NamespaceFinder::class)
            ->addArgument("ModernBx/Cli/App/Console/Command/");

        $this->containerBuilder
            ->autowire(PharConfigureCommand::class, PharConfigureCommand::class)
            ->setPublic(true);
        $this->containerBuilder
            ->autowire(PharCleanCommand::class, PharCleanCommand::class)
            ->setPublic(true);

        $this->containerBuilder
            ->autowire(Filesystem::class, Filesystem::class)
            ->setPublic(true);

        $this->containerBuilder->compile();

        return $this->containerBuilder;
    }
}
