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
            $this->executeInternal($input, $output);
            return static::CODE_SUCCESS;
        } catch (\Throwable $err) {
            $this->printer->error($err->getMessage());
            return $err->getCode();
        }
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
