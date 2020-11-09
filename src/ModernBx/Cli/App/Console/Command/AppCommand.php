<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command;

use ModernBx\Cli\Common\Console\GenericCommand;
use ModernBx\Cli\Common\Console\Printer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppCommand extends GenericCommand
{
    const CODE_SUCCESS = 0;
    const CODE_INVALID_ARGUMENT_VALUE = 1;
    const CODE_INVALID_OPTION_VALUE = 2;
    const CODE_IO_ERROR = 3;
    const CODE_INVALID_FILE_CONTENT = 4;

    /**
     * @var Printer
     */
    protected Printer $printer;

    /**
     * @var bool
     */
    protected bool $verbose = false;

    /**
     * @return bool
     */
    protected function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output): mixed
    {
        $this->printer = $this->getPrinter($output);
        $this->verbose = $input->getOption("verbose") !== false;

        try {
            $this->executeInternal($input, $output);
            return static::CODE_SUCCESS;
        } catch (\Throwable $err) {
            $this->printer->error($err->getMessage());
            return $err->getCode();
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        // Не делаем абстрактной - загрузчику классов необходимо получить инстанс
    }
}
