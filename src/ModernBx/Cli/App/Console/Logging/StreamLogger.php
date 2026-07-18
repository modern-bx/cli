<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength

namespace ModernBx\Cli\App\Console\Logging;

use Symfony\Contracts\Translation\TranslatorInterface;

final class StreamLogger implements LoggerInterface
{
    /** @var resource */
    private $stream;

    private ?TranslatorInterface $translator;

    /** @param resource $stream */
    public function __construct($stream, ?TranslatorInterface $translator = null)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Logger stream must be a valid resource.');
        }
        $this->stream = $stream;
        $this->translator = $translator;
    }

    public static function stderr(?TranslatorInterface $translator = null): self
    {
        return new self(STDERR, $translator);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $suffix = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        fwrite($this->stream, sprintf("[%s] %-7s %s%s\n", date('c'), $level, $this->translate($message, $context), $suffix));
    }

    /** @param array<string, mixed> $context */
    private function translate(string $message, array $context): string
    {
        if ($this->translator === null) {
            return $message;
        }

        /** @var array<string, string> $parameters */
        $parameters = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $parameters['%' . $key . '%'] = (string) $value;
            }
        }

        return $this->translator->trans($message, $parameters);
    }
}
