<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeleteCommand extends AppCommand
{
    protected static $defaultName = 'remote:delete';

    protected ProjectRegistry $projectRegistry;

    public function __construct(ProjectRegistry $projectRegistry, ?TranslatorInterface $translator = null)
    {
        $this->projectRegistry = $projectRegistry;

        parent::__construct($translator);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Удаляет зарегистрированный удаленный проект')
            ->setDefinition(
                new InputDefinition([
                    new InputArgument('codename', InputArgument::REQUIRED, 'Кодовое имя проекта'),
                ]),
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        /** @var string $codename */
        $codename = $input->getArgument('codename');

        if (!$this->projectRegistry->exists($codename)) {
            throw new \RuntimeException(sprintf('Проект не зарегистрирован: %s', $codename), static::CODE_IO_ERROR);
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(sprintf('Удалить проект %s? [y/N] ', $codename), false);

        if (!$helper->ask($input, $output, $question)) {
            return;
        }

        $this->projectRegistry->delete($codename);
        $this->printer->info(sprintf('Проект удален: %s', $codename));
    }
}
