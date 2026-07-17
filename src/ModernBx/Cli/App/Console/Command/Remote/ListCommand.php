<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListCommand extends AppCommand
{
    protected static $defaultName = 'remote:list';

    protected ProjectRegistry $projectRegistry;

    public function __construct(ProjectRegistry $projectRegistry, ?TranslatorInterface $translator = null)
    {
        $this->projectRegistry = $projectRegistry;

        parent::__construct($translator);
    }

    protected function configure(): void
    {
        $this->setDescription('Выводит список зарегистрированных удаленных проектов');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        foreach ($this->projectRegistry->list() as $project) {
            $this->printer->info($project);
        }
    }
}
