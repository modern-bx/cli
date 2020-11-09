<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\Compiler\Service;

use Symfony\Component\Finder\Finder;

final class NamespaceFinder
{
    /**
     * @var string
     */
    protected string $namespace;

    /**
     * @param string $namespace
     */
    public function __construct(string $namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @return \Generator<string>
     */
    public function findNamespaces(): \Generator
    {
        $finder = Finder::create();

        $finder->directories()->in($_SERVER["DOCUMENT_ROOT"] . "/src/" . $this->namespace);

        if ($finder->hasResults()) {
            foreach ($finder as $directory) {
                yield $directory->getFilename();
            }
        }
    }
}
