<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command;

use ModernBx\Cli\Common\Console\GenericCommand;
use ModernBx\Cli\Common\Console\Printer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class AppCommand extends GenericCommand
{
    const ENV_REMOTE = 'BX_CLI_REMOTE';
    const SESSION_REMOTE_DIR = 'modern-bx-cli';
    const SESSION_REMOTE_FILE_PREFIX = 'remote-';

    const CODE_SUCCESS = 0;
    const CODE_INVALID_ARGUMENT_VALUE = 1;
    const CODE_INVALID_OPTION_VALUE = 2;
    const CODE_IO_ERROR = 3;
    const CODE_INVALID_FILE_CONTENT = 4;

    /**
     * @var Printer
     */
    protected Printer $printer;

    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @var bool
     */
    protected bool $verbose = false;

    /**
     * @param TranslatorInterface|null $translator
     */
    public function __construct(?TranslatorInterface $translator = null)
    {
        $this->translator = $translator ?? $this->getDefaultTranslator();

        parent::__construct();
    }

    /**
     * @return TranslatorInterface
     */
    protected function getDefaultTranslator(): TranslatorInterface
    {
        $locale = $_SERVER["APP_LOCALE"] ?? getenv("APP_LOCALE") ?: "ru";
        $translator = new Translator($locale);
        $translator->addLoader("php", new PhpFileLoader());
        $translator->addResource("php", $_SERVER["DOCUMENT_ROOT"] . "/lang/ru/messages.php", "ru");
        $translator->addResource("php", $_SERVER["DOCUMENT_ROOT"] . "/lang/en/messages.php", "en");
        $translator->setFallbackLocales(["ru", "en"]);

        return $translator;
    }

    /**
     * @param string $key
     * @param array<string, string> $parameters
     * @return string
     */
    protected function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }

    /**
     * @return bool
     */
    protected function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output): mixed
    {
        $this->printer = $this->getPrinter($output);
        $this->verbose = $input->getOption("verbose") !== false;

        try {
            if ($this->applySessionRemote($input, $output)) {
                return static::CODE_SUCCESS;
            }

            $this->executeInternal($input, $output);
            return static::CODE_SUCCESS;
        } catch (\Throwable $err) {
            $this->printer->error($err->getMessage());
            return $err->getCode();
        }
    }

    protected function applySessionRemote(InputInterface $input, OutputInterface $output): bool
    {
        $sessionRemote = $this->getSessionRemote();

        if ($sessionRemote === null || $this->getName() === 'session:remote') {
            return false;
        }

        if (!$input->hasOption('remote')) {
            $output->writeln(sprintf(
                '<comment>Команда запущена в контексте remote "%s", но не поддерживает remote. Завершение.</comment>',
                $sessionRemote,
            ));

            return true;
        }

        if ($input->getOption('remote') === null) {
            $input->setOption('remote', $sessionRemote);
        }

        $remote = $input->getOption('remote');
        $output->writeln(sprintf(
            '<comment>Команда выполняется в контексте remote "%s".</comment>',
            is_string($remote) ? $remote : $sessionRemote,
        ));

        return false;
    }

    protected function getSessionRemote(): ?string
    {
        $remote = getenv(static::ENV_REMOTE) ?: ($_SERVER[static::ENV_REMOTE] ?? null);

        if (!is_string($remote) || trim($remote) === '') {
            $remote = $this->readSessionRemote();
        }

        if (!is_string($remote) || trim($remote) === '') {
            return null;
        }

        return trim($remote);
    }

    protected function setSessionRemote(string $remote): void
    {
        $path = $this->getSessionRemoteFilePath();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(
                sprintf('Не удалось создать директорию: %s', $directory),
                static::CODE_IO_ERROR,
            );
        }

        if (file_put_contents($path, $remote . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException(
                sprintf('Не удалось сохранить remote сессии: %s', $path),
                static::CODE_IO_ERROR,
            );
        }
    }

    protected function unsetSessionRemote(): void
    {
        $path = $this->getSessionRemoteFilePath();

        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException(sprintf('Не удалось удалить remote сессии: %s', $path), static::CODE_IO_ERROR);
        }
    }

    protected function readSessionRemote(): ?string
    {
        $path = $this->getSessionRemoteFilePath();

        if (!is_file($path)) {
            return null;
        }

        $remote = file_get_contents($path);

        return $remote === false ? null : trim($remote);
    }

    protected function getSessionRemoteFilePath(): string
    {
        return $this->getSessionRemoteDirectory()
            . DIRECTORY_SEPARATOR
            . static::SESSION_REMOTE_FILE_PREFIX
            . hash('sha256', $this->getTerminalSessionId());
    }

    protected function getSessionRemoteDirectory(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . static::SESSION_REMOTE_DIR;
    }

    protected function getTerminalSessionId(): string
    {
        $tty = function_exists('posix_ttyname') ? @posix_ttyname(STDIN) : false;

        if (is_string($tty) && $tty !== '') {
            return 'tty:' . $tty;
        }

        if (function_exists('posix_getppid')) {
            return 'ppid:' . (string) posix_getppid();
        }

        return 'pid:' . (string) getmypid();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        // Не делаем абстрактной - загрузчику классов необходимо получить инстанс
    }
}
