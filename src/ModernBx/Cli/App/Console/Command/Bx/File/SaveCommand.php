<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\File;

use ModernBx\Cli\App\Console\Command\Bx\KernelCommand;
use ModernBx\Cli\App\Service\ClassAliasLoader;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\to_json;

class SaveCommand extends KernelCommand
{
    use RemotePhpTrait;

    protected static $defaultName = 'file:save';

    public function __construct(
        ClassAliasLoader $aliasLoader,
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient
    ) {
        parent::__construct($aliasLoader);

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Сохраняет существующий файл в таблицу b_file')
            ->setHelp('Создает запись в b_file для существующего файла через CFile::MakeFileArray и CFile::SaveFile().')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('short', null, InputOption::VALUE_NONE, 'Вывести только ID созданной записи')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Путь к существующему файлу относительно document root проекта',
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $fileArgument = $input->getArgument('file');
        $short = $input->getOption('short') === true;

        if (!is_string($fileArgument)) {
            throw new \RuntimeException('Аргумент file должен быть строкой.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $path = $this->normalizeProjectPath($fileArgument);

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $row = $this->executeRemote($remote, $path);
        } else {
            parent::executeInternal($input, $output);
            $row = $this->executeLocal($path);
        }

        $this->printResult($row, $short);
    }

    /** @return array<string, mixed> */
    protected function executeLocal(string $path): array
    {
        $absolutePath = rtrim($this->getDocumentRoot()->toString(), '/') . $path;

        $this->assertReadableFile($absolutePath, $path);

        /** @phpstan-ignore-next-line */
        $file = \CFile::MakeFileArray($absolutePath);

        if (!is_array($file)) {
            throw new \RuntimeException(sprintf('Не удалось подготовить файл для сохранения: %s', $path));
        }

        /** @phpstan-ignore-next-line */
        $id = (int) \CFile::SaveFile($file, '');

        if ($id <= 0) {
            throw new \RuntimeException(sprintf('Не удалось сохранить файл в b_file: %s', $path));
        }

        return $this->fetchFileRow($id);
    }

    /** @return array<string, mixed> */
    protected function executeRemote(string $codename, string $path): array
    {
        $result = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($codename, $this->buildRemoteSaveCode($path)),
            'Не удалось сохранить файл удаленного проекта в b_file.',
        );

        if (!is_array($result)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректную строку b_file.');
        }

        return $result;
    }

    /** @return array<string, mixed> */
    protected function fetchFileRow(int $id): array
    {
        global $DB;

        $result = $DB->Query('SELECT * FROM b_file WHERE ID = ' . $id);
        $row = is_object($result) && method_exists($result, 'Fetch') ? $result->Fetch() : false;

        if (!is_array($row)) {
            throw new \RuntimeException(sprintf('Не удалось получить строку b_file для ID %d.', $id));
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    protected function printResult(array $row, bool $short): void
    {
        if ($short) {
            $id = $row['ID'] ?? '';
            $this->printer->info(is_scalar($id) ? (string) $id : '');
            return;
        }

        $this->printer->info($this->encodeFileRow($row));
    }

    /** @param array<string, mixed> $row */
    protected function encodeFileRow(array $row): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return (string) to_json($row, $flags);
    }

    protected function normalizeProjectPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new \RuntimeException('Путь к файлу не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $segments = [];

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(
                    'Путь к файлу не должен выходить за document root.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \RuntimeException('Путь должен указывать на файл.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return '/' . implode('/', $segments);
    }

    protected function assertReadableFile(string $absolutePath, string $path): void
    {
        if (!is_file($absolutePath)) {
            throw new \RuntimeException(sprintf('Файл не найден: %s', $path), static::CODE_IO_ERROR);
        }

        if (!is_readable($absolutePath)) {
            throw new \RuntimeException(sprintf('Файл недоступен для чтения: %s', $path), static::CODE_IO_ERROR);
        }
    }

    protected function buildRemoteSaveCode(string $path): string
    {
        return strtr(<<<'PHP_REMOTE'
<?php

$path = '__BX_CLI_FILE_SAVE_PATH__';
$bufferLevel = ob_get_level();
ob_start();

$send = static function (array $payload) use ($bufferLevel): void {
    while (ob_get_level() > $bufferLevel) {
        ob_end_clean();
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $flags);

    if (!is_string($json)) {
        $json = json_encode([
            'ok' => false,
            'error' => 'Не удалось сериализовать результат file:save: ' . json_last_error_msg(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    echo is_string($json) ? $json : '{"ok":false,"error":"Unable to encode file:save result."}';
};

try {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('DOCUMENT_ROOT не определен.');
    }

    $absolutePath = $documentRoot . $path;

    if (!is_file($absolutePath)) {
        throw new \RuntimeException('Файл не найден: ' . $path);
    }

    if (!is_readable($absolutePath)) {
        throw new \RuntimeException('Файл недоступен для чтения: ' . $path);
    }

    if (!class_exists('CFile')) {
        throw new \RuntimeException('Класс CFile недоступен на удаленном проекте.');
    }

    $file = \CFile::MakeFileArray($absolutePath);

    if (!is_array($file)) {
        throw new \RuntimeException('Не удалось подготовить файл для сохранения: ' . $path);
    }

    $id = (int) \CFile::SaveFile($file, '');

    if ($id <= 0) {
        throw new \RuntimeException('Не удалось сохранить файл в b_file: ' . $path);
    }

    global $DB;
    $dbResult = $DB->Query('SELECT * FROM b_file WHERE ID = ' . $id);
    $row = is_object($dbResult) && method_exists($dbResult, 'Fetch') ? $dbResult->Fetch() : false;

    if (!is_array($row)) {
        throw new \RuntimeException('Не удалось получить строку b_file для ID ' . $id . '.');
    }

    $send(['ok' => true, 'result' => $row]);
} catch (\Throwable $err) {
    $send(['ok' => false, 'error' => $err->getMessage()]);
}
PHP_REMOTE, [
            "'__BX_CLI_FILE_SAVE_PATH__'" => var_export($path, true),
        ]);
    }
}
