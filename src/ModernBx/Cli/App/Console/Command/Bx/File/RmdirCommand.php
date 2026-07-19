<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\File;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class RmdirCommand extends DeleteCommand
{
    protected static $defaultName = 'file:rmdir';

    protected function configure(): void
    {
        $this
            ->setDescription('Удаляет директорию из файловой структуры проекта')
            ->setHelp('Удаляет директорию по пути относительно document root локального или удаленного проекта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addArgument('path', InputArgument::REQUIRED, 'Путь к директории относительно document root проекта');
    }
}
