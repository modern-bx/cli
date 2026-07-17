<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PhpExecCommand extends KernelCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'php:exec';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans('command.php_exec.description'))
            ->setHelp($this->trans('command.php_exec.help'));
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

        $code = (string) stream_get_contents(STDIN);
        $file = tempnam(sys_get_temp_dir(), 'modern-bx-php-exec-');

        if ($file === false) {
            throw new \Exception('Unable to create temporary PHP file.');
        }

        try {
            file_put_contents($file, "<?php
" . $code);
            require $file;
        } finally {
            unlink($file);
        }
    }
}
