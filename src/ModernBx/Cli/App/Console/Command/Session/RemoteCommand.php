<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Session;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoteCommand extends AppCommand
{
    protected static $defaultName = 'session:remote';

    protected ProjectRegistry $projectRegistry;

    public function __construct(ProjectRegistry $projectRegistry)
    {
        parent::__construct();

        $this->projectRegistry = $projectRegistry;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Выводит shell-команду для выбора remote в текущей терминальной сессии')
            ->setHelp('Выполните вывод команды через eval, чтобы переменная окружения сохранилась в текущем shell.')
            ->addOption('unset', null, InputOption::VALUE_NONE, 'Сбросить remote текущей терминальной сессии')
            ->addArgument('remote', InputArgument::OPTIONAL, 'Кодовое имя удаленного проекта');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        $remote = $input->getArgument('remote');
        $unset = $input->getOption('unset') === true;

        if ($unset) {
            putenv(static::ENV_REMOTE);
            unset($_SERVER[static::ENV_REMOTE]);
            $this->printer->info('unset ' . static::ENV_REMOTE);
            return;
        }

        if ($remote === null) {
            $sessionRemote = $this->getSessionRemote();
            $this->printer->info($sessionRemote !== null
                ? sprintf('Текущий remote: %s', $sessionRemote)
                : 'Remote текущей терминальной сессии не указан.');
            return;
        }

        if (!is_string($remote) || trim($remote) === '') {
            throw new \RuntimeException(
                'Кодовое имя remote должно быть непустой строкой.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $remote = trim($remote);

        if (!$this->projectRegistry->exists($remote)) {
            throw new \RuntimeException(sprintf('Проект не зарегистрирован: %s', $remote));
        }

        putenv(static::ENV_REMOTE . '=' . $remote);
        $_SERVER[static::ENV_REMOTE] = $remote;
        $this->printer->info(sprintf("export %s='%s'", static::ENV_REMOTE, str_replace("'", "'\\''", $remote)));
    }
}
