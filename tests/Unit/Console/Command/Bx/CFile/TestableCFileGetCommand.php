<?php

declare(strict_types=1);

namespace ModernBx\Cli\Tests\Unit\Console\Command\Bx\CFile;

use ModernBx\Cli\App\Console\Command\Bx\CFile\GetCommand;
use ModernBx\Cli\Common\Console\Printer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class TestableCFileGetCommand extends GetCommand
{
    /** @var array<int, array{remote: string, code: string}> */
    public array $remoteExecutions = [];

    /** @var list<string> */
    public array $remoteResponses = [];

    public function runRemote(ArrayInput $input, string $remote): string
    {
        $output = new BufferedOutput();
        $this->printer = new Printer($output);
        $idArgument = $input->getArgument('id');

        if (!is_string($idArgument) && !is_int($idArgument)) {
            throw new \RuntimeException('Аргумент ID должен быть числом.');
        }

        $row = $this->executeRemote($remote, $this->normalizeId((string) $idArgument));
        $this->printer->info($this->encodeFileRow($row));

        return $output->fetch();
    }

    protected function executeRemotePhp(string $codename, string $code): string
    {
        $this->remoteExecutions[] = [
            'remote' => $codename,
            'code' => $code,
        ];

        return array_shift($this->remoteResponses) ?? '{"ok":true,"result":{}}';
    }
}
