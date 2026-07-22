<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RenameCommand extends AppCommand
{
    protected static $defaultName = 'remote:rename';

    protected ProjectRegistry $projectRegistry;

    public function __construct(ProjectRegistry $projectRegistry, ?TranslatorInterface $translator = null)
    {
        $this->projectRegistry = $projectRegistry;

        parent::__construct($translator);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Переименовывает зарегистрированный удаленный проект')
            ->setDefinition(
                new InputDefinition([
                    new InputArgument('prev', InputArgument::REQUIRED, 'Текущее кодовое имя проекта'),
                    new InputArgument('next', InputArgument::REQUIRED, 'Новое кодовое имя проекта'),
                ]),
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        /** @var string $prev */
        $prev = $input->getArgument('prev');
        /** @var string $next */
        $next = $input->getArgument('next');

        if (!$this->projectRegistry->exists($prev)) {
            throw new \RuntimeException(sprintf('Проект не зарегистрирован: %s', $prev), static::CODE_IO_ERROR);
        }

        if ($this->projectRegistry->exists($next)) {
            throw new \RuntimeException(sprintf('Кодовое имя проекта уже занято: %s', $next), static::CODE_IO_ERROR);
        }

        if (!$this->projectRegistry->isValidCodename($next)) {
            throw new \RuntimeException(
                'Кодовое имя проекта должно содержать только латинские буквы, цифры, точки, дефисы и подчеркивания.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $this->projectRegistry->rename($prev, $next);
        $this->printer->info(sprintf('Проект переименован: %s -> %s', $prev, $next));
    }
}
