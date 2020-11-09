<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;

final class CommandFinder
{
    /**
     * @var string
     */
    protected string $namespace;

    /**
     * @var string
     */
    protected string $suffix;

    /**
     * @var RuntimeInfo
     */
    protected RuntimeInfo $runtimeInfo;

    /**
     * @param string $namespace
     * @param string $suffix
     * @param RuntimeInfo $runtimeInfo
     */
    public function __construct(string $namespace, string $suffix, RuntimeInfo $runtimeInfo)
    {
        $this->namespace = $namespace;
        $this->suffix = $suffix;
        $this->runtimeInfo = $runtimeInfo;
    }

    /**
     * @return \Generator<string, string>
     * @throws \ReflectionException
     */
    public function findCommands(): \Generator
    {
        $finder = Finder::create();

        $finder->files()->in($_SERVER["DOCUMENT_ROOT"] . "/src/" . $this->namespace);

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $bundlePart = $file->getRelativePath();

                if ($bundlePart) {
                    $bundlePart .= "/";
                }

                /** @var class-string $klass */
                $klass = str_replace(
                    "/",
                    "\\",
                    $this->namespace . $bundlePart . substr($file->getFilename(), 0, - 4)
                );

                $klassName = $this->runtimeInfo->getScopedClass($klass);
                $reflection = new \ReflectionClass($klassName);
                /** @var Command $instance */
                $instance = $reflection->newInstanceWithoutConstructor();
                $command = $instance->getDefaultName();

                if ($command) {
                    yield $command => $klassName;
                }
            }
        }
    }
}
