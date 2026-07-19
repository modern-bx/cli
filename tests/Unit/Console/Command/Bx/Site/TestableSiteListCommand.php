<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\Site;

use ModernBx\Cli\App\Console\Command\Bx\Site\ListCommand;
use ModernBx\Cli\Common\Console\Printer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class TestableSiteListCommand extends ListCommand
{
    /** @var array<int, array{remote: string, code: string}> */
    public array $remoteExecutions = [];

    /** @var list<string> */
    public array $remoteResponses = [];

    public function runRemote(ArrayInput $input, string $remote): string
    {
        $output = new BufferedOutput();
        $this->printer = new Printer($output);
        $this->executeRemote($input, $remote);

        return $output->fetch();
    }

    protected function executeRemotePhp(string $codename, string $code): string
    {
        $this->remoteExecutions[] = [
            'remote' => $codename,
            'code' => $code,
        ];

        return array_shift($this->remoteResponses) ?? '{"ok":true,"result":[]}';
    }
}
