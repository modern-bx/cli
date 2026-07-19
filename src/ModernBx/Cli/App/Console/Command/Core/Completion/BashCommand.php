<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Core\Completion;

use ModernBx\Cli\App\Console\Command\AppCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BashCommand extends AppCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'completion:bash';

    protected function configure(): void
    {
        $this
            ->setDescription('Generate bash completion script')
            ->setHelp('Prints a bash completion script for the executable name passed in the argument.')
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'executable',
                        InputArgument::OPTIONAL,
                        'Executable name used to register completion.',
                        'bx-cli',
                    ),
                ]),
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        /** @var string $executable */
        $executable = $input->getArgument('executable');
        $output->write($this->buildCompletionScript($executable));
    }

    protected function buildCompletionScript(string $executable): string
    {
        $escapedExecutable = str_replace("'", "'\\''", $executable);

        return str_replace(
            '__BX_CLI_EXECUTABLE__',
            $escapedExecutable,
            <<<'BASH'
# bash completion for bx-cli

_bx_cli_commands()
{
    "${COMP_WORDS[0]}" list --raw 2>/dev/null | awk '{ print $1 }'
}

_bx_cli_options()
{
    local command="$1"

    {
        "${COMP_WORDS[0]}" help "$command" --format=txt 2>/dev/null \
            | sed -n \
                -e 's/^[[:space:]]*\(-[-[:alnum:]][-[:alnum:]]*\).*/\1/p' \
                -e 's/^[[:space:]]*-[^,]*,[[:space:]]*\(--[-[:alnum:]][-[:alnum:]]*\).*/\1/p'
        printf '%s\n' --help -h --quiet -q --verbose -v --version -V --ansi --no-ansi --no-interaction -n
    } | sort -u
}

_bx_cli_remotes()
{
    "${COMP_WORDS[0]}" remote:list 2>/dev/null
}

_bx_cli_previous_word()
{
    local index=$((COMP_CWORD - 1))
    if [[ "$index" -ge 0 ]]; then
        printf '%s' "${COMP_WORDS[$index]}"
    fi
}

_bx_cli()
{
    local cur cword

    cur="${COMP_WORDS[COMP_CWORD]}"
    cword="$COMP_CWORD"

    if [[ "$cword" -eq 1 ]]; then
        mapfile -t COMPREPLY < <(compgen -W "$(_bx_cli_commands)" -- "$cur")
        return 0
    fi

    if [[ "$cur" == --remote=* ]]; then
        mapfile -t COMPREPLY < <(compgen -W "$(_bx_cli_remotes)" -- "${cur#--remote=}")
        COMPREPLY=("${COMPREPLY[@]/#/--remote=}")
        return 0
    fi

    if [[ "$(_bx_cli_previous_word)" == --remote ]]; then
        mapfile -t COMPREPLY < <(compgen -W "$(_bx_cli_remotes)" -- "$cur")
        return 0
    fi

    if [[ "${COMP_WORDS[1]}" == session:remote || "${COMP_WORDS[1]}" == remote:delete ]]; then
        mapfile -t COMPREPLY < <(compgen -W "$(_bx_cli_remotes)" -- "$cur")
        return 0
    fi

    if [[ "$cur" == -* ]]; then
        mapfile -t COMPREPLY < <(compgen -W "$(_bx_cli_options "${COMP_WORDS[1]}")" -- "$cur")
        return 0
    fi

    COMPREPLY=()
    return 0
}

complete -F _bx_cli '__BX_CLI_EXECUTABLE__'
BASH
        );
    }
}
