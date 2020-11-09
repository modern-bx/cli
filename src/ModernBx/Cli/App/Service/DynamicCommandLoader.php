<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service;

use Psr\Container\ContainerInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

final class DynamicCommandLoader implements CommandLoaderInterface
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var array<string, string>
     */
    protected array $commandMap = [];

    /**
     * @param ContainerInterface $container
     * @param CommandFinder $finder
     * @throws \ReflectionException
     */
    public function __construct(ContainerInterface $container, CommandFinder $finder)
    {
        $this->container = $container;

        $this->findCommands($finder);
    }

    /**
     * @param CommandFinder $finder
     * @return void
     * @throws \ReflectionException
     */
    protected function findCommands(CommandFinder $finder): void
    {
        foreach ($finder->findCommands() as $id => $klass) {
            $this->commandMap[$id] = $klass;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (!$this->has($name)) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist', $name));
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        /** @var Command $command */
        $command = $this->container->get($name);

        return $command;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name): bool
    {
        return isset($this->commandMap[$name]) && $this->container->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getNames(): array
    {
        return array_keys($this->commandMap);
    }
}
