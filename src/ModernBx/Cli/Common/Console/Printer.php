<?php

declare(strict_types=1);

namespace ModernBx\Cli\Common\Console;

use Symfony\Component\Console\Output\OutputInterface;
use function ModernBx\CommonFunctions\format;

final class Printer
{
    /**
     * @var OutputInterface
     */
    protected OutputInterface $output;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param string|string[] $message
     * @param string $tag
     */
    public function put(string|array $message, string $tag = "info"): void
    {
        $message = (array) $message;

        foreach ($message as $line) {
            $this->output->writeln(format("<{tag}>{line}</{tag}>", [
                "tag" => $tag,
                "line"=> $line,
            ]));
        }
    }

    /**
     * @param string $message
     * @param array<string> $pairs
     * @param string $tag
     */
    public function putFormat(string $message, array $pairs, string $tag = "info"): void
    {
        $this->output->writeln(format("<{tag}>{line}</{tag}>", [
            "tag" => $tag,
            "line"=> format($message, $pairs),
        ]));
    }

    /**
     * @param string $message
     * @param array<string> $pairs
     */
    public function formatInfo(string $message, array $pairs): void
    {
        $this->putFormat($message, $pairs);
    }

    /**
     * @param string $message
     * @param array<string> $pairs
     * @noinspection PhpUnused
     */
    public function formatError(string $message, array $pairs): void
    {
        $this->putFormat($message, $pairs, "error");
    }

    /**
     * @param string|string[] $message
     */
    public function error(array|string $message): void
    {
        $this->put($message, "error");
    }

    /**
     * @param string|string[] $message
     */
    public function info(array|string $message): void
    {
        $this->put($message);
    }
}
