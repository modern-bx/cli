<?php

declare(strict_types=1);

namespace ModernBx\Cli\Compiler\Console\Command;

use ModernBx\Cli\Common\Console\GenericCommand;
use ModernBx\Cli\Compiler\Service\NamespaceFinder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class PharCleanCommand extends GenericCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'phar:clean';

    /**
     * @var NamespaceFinder
     */
    protected NamespaceFinder $namespaceFinder;

    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @param NamespaceFinder $namespaceFinder
     * @param Filesystem $filesystem
     */
    public function __construct(NamespaceFinder $namespaceFinder, Filesystem $filesystem)
    {
        parent::__construct(static::$defaultName);

        $this->namespaceFinder = $namespaceFinder;
        $this->filesystem = $filesystem;
    }

    protected function configure(): void
    {
        $this
            ->setDescription("Clean up after building Phar")
            ->setHelp("Delete box.json and .box_dump");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->filesystem->remove([
            $_SERVER["DOCUMENT_ROOT"] . "/box.json",
            $_SERVER["DOCUMENT_ROOT"] . "/.box_dump"
        ]);

        return 0;
    }
}
