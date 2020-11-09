<?php

declare(strict_types=1);

namespace ModernBx\Cli\Common\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class GenericCommand extends Command
{
    /**
     * @param OutputInterface $output
     * @return Printer
     */
    protected function getPrinter(OutputInterface $output): Printer
    {
        return new Printer($output);
    }
}
