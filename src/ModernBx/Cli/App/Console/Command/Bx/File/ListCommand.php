<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\File;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends BxCommand
{
    protected static $defaultName = 'file:list';

    protected RemoteProjectConfigManager $remoteProjectConfigManager;

    protected BitrixAdminClient $bitrixAdminClient;

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient
    ) {
        parent::__construct();

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Выводит список файлов из файловой структуры проекта')
            ->setHelp('Expr может быть путем к директории или glob-выражением относительно document root.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('short', null, InputOption::VALUE_NONE, 'Вывести только короткие имена файлов')
            ->addArgument('expr', InputArgument::REQUIRED, 'Путь к директории или glob-выражение');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $exprArgument = $input->getArgument('expr');
        $short = $input->getOption('short') === true;

        if (!is_string($exprArgument)) {
            throw new \RuntimeException('Аргумент expr должен быть строкой.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $items = $this->executeRemote($remote, $exprArgument);
        } else {
            parent::executeInternal($input, $output);
            $items = $this->executeLocal($exprArgument);
        }

        $this->printItems($items, $short);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function executeLocal(string $expr): array
    {
        $documentRoot = rtrim((string) $this->getDocumentRoot(), '/');
        $relativeExpr = $this->normalizeExpr($expr);
        $fullExpr = $documentRoot . $relativeExpr;
        $paths = is_dir($fullExpr) ? $this->scanDirectory($fullExpr) : glob($fullExpr, GLOB_BRACE);

        if ($paths === false || $paths === []) {
            throw new \RuntimeException(sprintf('Файлы не найдены: %s', $expr), static::CODE_IO_ERROR);
        }

        sort($paths, SORT_STRING);

        return array_map(
            fn (string $path): array => $this->buildLocalFileInfo($documentRoot, $path),
            array_values($paths),
        );
    }

    /**
     * @return string[]
     */
    protected function scanDirectory(string $directory): array
    {
        $items = scandir($directory);

        if ($items === false) {
            throw new \RuntimeException(
                sprintf('Не удалось прочитать директорию: %s', $directory),
                static::CODE_IO_ERROR,
            );
        }

        $items = array_values(array_filter(
            $items,
            static fn (string $item): bool => $item !== '.' && $item !== '..',
        ));

        return array_map(
            static fn (string $item): string => rtrim($directory, '/') . '/' . $item,
            $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLocalFileInfo(string $documentRoot, string $path): array
    {
        $stat = stat($path);

        if ($stat === false) {
            throw new \RuntimeException(sprintf('Не удалось прочитать информацию о файле: %s', $path));
        }

        return [
            'name' => basename($path),
            'path' => '/' . ltrim(substr($path, strlen($documentRoot)), '/'),
            'type' => is_dir($path) ? 'd' : (is_link($path) ? 'l' : '-'),
            'perms' => $this->formatPermissions((int) $stat['mode']),
            'links' => (int) $stat['nlink'],
            'owner' => (string) $stat['uid'],
            'group' => (string) $stat['gid'],
            'size' => (int) $stat['size'],
            'mtime' => (int) $stat['mtime'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function executeRemote(string $codename, string $expr): array
    {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        try {
            $json = $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $this->buildRemoteListCode($expr));
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $json = $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $this->buildRemoteListCode($expr));
        }

        $result = json_decode($json, true);

        if (!is_array($result)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный JSON.');
        }

        if (($result['ok'] ?? false) !== true) {
            $error = $result['error'] ?? 'Не удалось получить список файлов удаленного проекта.';
            throw new \RuntimeException(
                is_string($error) ? $error : 'Не удалось получить список файлов удаленного проекта.',
            );
        }

        $items = $result['items'] ?? [];

        if (!is_array($items)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный список файлов.');
        }

        return array_values(array_filter($items, 'is_array'));
    }

    protected function buildRemoteListCode(string $expr): string
    {
        $encodedExpr = base64_encode($expr);
        $code = <<<'PHP'
$expr = base64_decode('__EXPR__', true);

if (!is_string($expr)) {
    echo json_encode(['ok' => false, 'error' => 'Некорректное glob-выражение.'], JSON_UNESCAPED_UNICODE);
    return;
}

$formatPermissions = static function (int $perms): string {
    $map = [
        0400 => 'r', 0200 => 'w', 0100 => 'x',
        0040 => 'r', 0020 => 'w', 0010 => 'x',
        0004 => 'r', 0002 => 'w', 0001 => 'x',
    ];
    $value = '';

    foreach ($map as $bit => $char) {
        $value .= ($perms & $bit) !== 0 ? $char : '-';
    }

    return $value;
};

$normalizeExpr = static function (string $path): string {
    $path = trim(str_replace('\\', '/', $path));

    if ($path === '') {
        throw new \RuntimeException('Путь expr не должен быть пустым.');
    }

    foreach (explode('/', ltrim($path, '/')) as $segment) {
        if ($segment === '..') {
            throw new \RuntimeException('Путь expr не должен выходить за document root.');
        }
    }

    return '/' . ltrim($path, '/');
};

try {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($documentRoot === '') {
        throw new \RuntimeException('Не удалось определить document root удаленного проекта.');
    }

    $relativeExpr = $normalizeExpr($expr);
    $fullExpr = $documentRoot . $relativeExpr;
    $paths = is_dir($fullExpr)
        ? array_map(
            static fn (string $item): string => rtrim($fullExpr, '/') . '/' . $item,
            array_values(array_filter(
                scandir($fullExpr) ?: [],
                static fn (string $item): bool => $item !== '.' && $item !== '..',
            )),
        )
        : (glob($fullExpr, GLOB_BRACE) ?: []);

    if ($paths === []) {
        throw new \RuntimeException('Файлы не найдены: ' . $expr);
    }

    sort($paths, SORT_STRING);
    $items = [];

    foreach ($paths as $path) {
        $stat = @stat($path);

        if ($stat === false) {
            continue;
        }

        $items[] = [
            'name' => basename($path),
            'path' => '/' . ltrim(substr($path, strlen($documentRoot)), '/'),
            'type' => is_dir($path) ? 'd' : (is_link($path) ? 'l' : '-'),
            'perms' => $formatPermissions((int) $stat['mode']),
            'links' => (int) $stat['nlink'],
            'owner' => (string) $stat['uid'],
            'group' => (string) $stat['gid'],
            'size' => (int) $stat['size'],
            'mtime' => (int) $stat['mtime'],
        ];
    }

    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $error) {
    echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
PHP;

        return str_replace('__EXPR__', $encodedExpr, $code);
    }

    protected function normalizeExpr(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new \RuntimeException('Путь expr не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '..') {
                throw new \RuntimeException(
                    'Путь expr не должен выходить за document root.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function printItems(array $items, bool $short): void
    {
        foreach ($items as $item) {
            $this->printer->info($short ? $this->formatShortItem($item) : $this->formatLongItem($item));
        }
    }

    /** @param array<string, mixed> $item */
    protected function formatShortItem(array $item): string
    {
        $name = $item['name'] ?? '';

        return is_scalar($name) ? (string) $name : '';
    }

    /** @param array<string, mixed> $item */
    protected function formatLongItem(array $item): string
    {
        return sprintf(
            '%s%s %3d %-8s %-8s %10d %s %s',
            $this->readString($item, 'type'),
            $this->readString($item, 'perms'),
            $this->readInt($item, 'links'),
            $this->readString($item, 'owner'),
            $this->readString($item, 'group'),
            $this->readInt($item, 'size'),
            date('M d H:i', $this->readInt($item, 'mtime')),
            $this->readString($item, 'name'),
        );
    }

    protected function formatPermissions(int $perms): string
    {
        $map = [
            0400 => 'r',
            0200 => 'w',
            0100 => 'x',
            0040 => 'r',
            0020 => 'w',
            0010 => 'x',
            0004 => 'r',
            0002 => 'w',
            0001 => 'x',
        ];
        $value = '';

        foreach ($map as $bit => $char) {
            $value .= ($perms & $bit) !== 0 ? $char : '-';
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    protected function readString(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /** @param array<string, mixed> $values */
    protected function readInt(array $values, string $key): int
    {
        $value = $values[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
