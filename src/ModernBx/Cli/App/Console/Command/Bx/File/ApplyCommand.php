<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\File;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use ModernBx\Cli\App\Service\Remote\RemoteFileApplyPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteFilePhpCodeBuilder;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ApplyCommand extends BxCommand
{
    protected static $defaultName = 'file:apply';

    protected RemoteProjectConfigManager $remoteProjectConfigManager;

    protected BitrixAdminClient $bitrixAdminClient;

    protected RemoteFileApplyPhpCodeBuilder $remoteFileApplyPhpCodeBuilder;

    protected RemoteFilePhpCodeBuilder $remoteFilePhpCodeBuilder;

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteFileApplyPhpCodeBuilder $remoteFileApplyPhpCodeBuilder,
        RemoteFilePhpCodeBuilder $remoteFilePhpCodeBuilder
    ) {
        parent::__construct();

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteFileApplyPhpCodeBuilder = $remoteFileApplyPhpCodeBuilder;
        $this->remoteFilePhpCodeBuilder = $remoteFilePhpCodeBuilder;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Загружает директорию в файловую структуру проекта')
            ->setHelp('Копирует структуру локальной директории в путь относительно document root проекта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Перезаписать существующие файлы')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Не запрашивать подтверждение при замечаниях')
            ->addArgument('src', InputArgument::REQUIRED, 'Локальная директория-источник')
            ->addArgument('dest', InputArgument::REQUIRED, 'Директория назначения относительно document root проекта');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $srcArgument = $input->getArgument('src');
        $destArgument = $input->getArgument('dest');

        if (!is_string($srcArgument) || !is_string($destArgument)) {
            throw new \RuntimeException(
                'Аргументы src и dest должны быть строками.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $src = $this->resolveSourceDirectory($srcArgument);
        $dest = $this->normalizeProjectPath($destArgument);
        $force = $input->getOption('force') === true;
        $yes = $input->getOption('yes') === true;
        $plan = $this->buildPlan($src, $dest);

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($remote, $src, $dest, $plan, $force, $yes, $input, $output);
            return;
        }

        parent::executeInternal($input, $output);
        $this->executeLocal($src, $dest, $plan, $force, $yes, $input, $output);
    }

    /** @param array{directories: string[], files: array<int, array{relative: string, path: string, size: int}>} $plan */
    protected function executeLocal(
        string $src,
        string $dest,
        array $plan,
        bool $force,
        bool $yes,
        InputInterface $input,
        OutputInterface $output
    ): void {
        $documentRoot = rtrim((string) $this->getDocumentRoot(), '/');
        $diagnostics = $this->diagnoseLocal($documentRoot, $dest, $plan, $force);
        $this->printDiagnostics($diagnostics);
        $this->ensureCanContinue($diagnostics, $src, $dest, $yes, $input, $output);

        $createdDirectories = $this->createLocalDirectories($documentRoot, $dest, $plan['directories']);
        $stats = $this->copyLocalFiles($documentRoot, $dest, $plan['files'], $output);

        $this->printSummary($stats['files'], $stats['bytes'], $createdDirectories);
    }

    /** @param array{directories: string[], files: array<int, array{relative: string, path: string, size: int}>} $plan */
    protected function executeRemote(
        string $codename,
        string $src,
        string $dest,
        array $plan,
        bool $force,
        bool $yes,
        InputInterface $input,
        OutputInterface $output
    ): void {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);

        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        /** @var array{notices: string[], errors: string[]} $diagnostics */
        $diagnostics = $this->withRemoteSessionRetry($codename, $config, $endpoint, $sessionId, function (
            string $sessionId
        ) use (
            $endpoint,
            $dest,
            $plan,
            $force
): array {
            return $this->diagnoseRemote($endpoint, $sessionId, $dest, $plan, $force);
        });
        /** @var array{upload_max_filesize: string, post_max_size: string, max_post_file_bytes: int} $limits */
        $limits = $this->withRemoteSessionRetry($codename, $config, $endpoint, $sessionId, function (
            string $sessionId
        ) use (
            $endpoint
): array {
            return $this->loadRemoteUploadLimits($endpoint, $sessionId);
        });
        $diagnostics['errors'] = array_merge(
            $diagnostics['errors'],
            $this->diagnoseRemoteUploadLimits($plan['files'], $limits),
        );
        $this->printDiagnostics($diagnostics);
        $this->ensureCanContinue($diagnostics, $src, $dest, $yes, $input, $output);

        /** @var int $createdDirectories */
        $createdDirectories = $this->withRemoteSessionRetry($codename, $config, $endpoint, $sessionId, function (
            string $sessionId
        ) use (
            $endpoint,
            $dest,
            $plan
): int {
            return $this->createRemoteDirectories($endpoint, $sessionId, $dest, $plan['directories']);
        });
        $stats = $this->uploadRemoteFiles($codename, $config, $endpoint, $sessionId, $dest, $plan['files'], $output);

        $this->printSummary($stats['files'], $stats['bytes'], $createdDirectories);
    }

    /**
     * @return array{directories: string[], files: array<int, array{relative: string, path: string, size: int}>}
     */
    protected function buildPlan(string $src, string $dest): array
    {
        $directories = [''];
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }

            $path = $item->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($src) + 1));

            if ($relative === '') {
                continue;
            }

            if ($item->isDir()) {
                $directories[] = $relative;
                continue;
            }

            if ($item->isFile()) {
                $files[] = [
                    'relative' => $relative,
                    'path' => $path,
                    'size' => $item->getSize(),
                ];
            }
        }

        foreach ($files as $file) {
            $directory = dirname($file['relative']);

            if ($directory !== '.') {
                $directories[] = str_replace('\\', '/', $directory);
            }
        }

        $directories = array_values(array_unique($directories));
        sort($directories, SORT_STRING);
        usort($files, static fn (array $a, array $b): int => strcmp($a['relative'], $b['relative']));

        return ['directories' => $directories, 'files' => $files];
    }

    protected function resolveSourceDirectory(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new \RuntimeException('Путь src не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $absolutePath = $path[0] === '/' ? $path : getcwd() . '/' . $path;
        $realPath = realpath($absolutePath);

        if ($realPath === false || !is_dir($realPath)) {
            throw new \RuntimeException(sprintf('Локальная директория не найдена: %s', $path), static::CODE_IO_ERROR);
        }

        if (!is_readable($realPath)) {
            throw new \RuntimeException(
                sprintf('Локальная директория недоступна для чтения: %s', $path),
                static::CODE_IO_ERROR,
            );
        }

        return $realPath;
    }

    protected function normalizeProjectPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new \RuntimeException('Путь dest не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $segments = [];

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(
                    'Путь dest не должен выходить за document root.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \RuntimeException(
                'Путь dest должен указывать на директорию.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        return '/' . implode('/', $segments);
    }

    /**
     * @param array{directories: string[], files: array<int, array{relative: string, path: string, size: int}>} $plan
     * @return array{notices: string[], errors: string[]}
     */
    protected function diagnoseLocal(string $documentRoot, string $dest, array $plan, bool $force): array
    {
        $notices = [];
        $errors = [];

        foreach ($plan['directories'] as $directory) {
            $target = $this->joinProjectPath($dest, $directory);
            $fullTarget = $documentRoot . $target;

            if (is_dir($fullTarget)) {
                $notices[] = sprintf('Директория уже существует: %s', $target);
            } elseif (file_exists($fullTarget)) {
                $errors[] = sprintf('На месте директории существует файл: %s', $target);
            }
        }

        foreach ($plan['files'] as $file) {
            $target = $this->joinProjectPath($dest, $file['relative']);
            $fullTarget = $documentRoot . $target;
            $message = sprintf(
                'Файл уже существует: %s (%s (%d байт))',
                $target,
                $this->formatBytes($file['size']),
                $file['size'],
            );

            if (is_file($fullTarget)) {
                $force ? $notices[] = $message : $errors[] = $message;
            } elseif (is_dir($fullTarget)) {
                $errors[] = sprintf('На месте файла существует директория: %s', $target);
            }
        }

        return ['notices' => $notices, 'errors' => $errors];
    }

    /**
     * @param array{directories: string[], files: array<int, array{relative: string, path: string, size: int}>} $plan
     * @return array{notices: string[], errors: string[]}
     */
    protected function diagnoseRemote(
        string $endpoint,
        string $sessionId,
        string $dest,
        array $plan,
        bool $force
    ): array {
        $code = $this->remoteFileApplyPhpCodeBuilder->buildDiagnose(
            $dest,
            $plan['directories'],
            array_map(static fn (array $file): array => [
                'relative' => $file['relative'],
                'size' => $file['size'],
            ], $plan['files']),
            $force,
        );
        $json = $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        $result = json_decode($json, true);

        if (!is_array($result) || ($result['ok'] ?? false) !== true) {
            $error = is_array($result) && is_string($result['error'] ?? null)
                ? $result['error']
                : 'Диагностика не удалась.';
            throw new \RuntimeException($error);
        }

        return [
            'notices' => array_values(array_filter($result['notices'] ?? [], 'is_string')),
            'errors' => array_values(array_filter($result['errors'] ?? [], 'is_string')),
        ];
    }

    /**
     * @return array{
     *     upload_max_filesize: string,
     *     post_max_size: string,
     *     max_post_file_bytes: int
     * }
     */
    protected function loadRemoteUploadLimits(string $endpoint, string $sessionId): array
    {
        $json = $this->bitrixAdminClient->executePhp(
            $endpoint,
            $sessionId,
            $this->remoteFilePhpCodeBuilder->buildUploadLimits(),
        );
        $result = json_decode($json, true);

        if (!is_array($result) || ($result['ok'] ?? false) !== true) {
            $error = is_array($result) && is_string($result['error'] ?? null)
                ? $result['error']
                : 'Не удалось получить ограничения загрузки удаленного проекта.';
            throw new \RuntimeException($error);
        }

        return [
            'upload_max_filesize' => is_string($result['upload_max_filesize'] ?? null)
                ? $result['upload_max_filesize']
                : '',
            'post_max_size' => is_string($result['post_max_size'] ?? null) ? $result['post_max_size'] : '',
            'max_post_file_bytes' => (int) ($result['max_post_file_bytes'] ?? 0),
        ];
    }

    /**
     * @param array<int, array{relative: string, path: string, size: int}> $files
     * @param array{upload_max_filesize: string, post_max_size: string, max_post_file_bytes: int} $limits
     * @return string[]
     */
    protected function diagnoseRemoteUploadLimits(array $files, array $limits): array
    {
        $limit = $limits['max_post_file_bytes'];

        if ($limit <= 0) {
            return [];
        }

        $errors = [];

        foreach ($files as $file) {
            if ($file['size'] <= $limit) {
                continue;
            }

            $errors[] = sprintf(
                'Файл превышает лимит загрузки PHP: %s (%s (%d байт)) > %s '
                    . '(%d байт; upload_max_filesize=%s, post_max_size=%s).',
                $file['relative'],
                $this->formatBytes($file['size']),
                $file['size'],
                $this->formatBytes($limit),
                $limit,
                $limits['upload_max_filesize'],
                $limits['post_max_size'],
            );
        }

        return $errors;
    }

    /** @param array{notices: string[], errors: string[]} $diagnostics */
    protected function printDiagnostics(array $diagnostics): void
    {
        foreach ($diagnostics['notices'] as $notice) {
            $this->printer->info('NOTICE: ' . $notice);
        }

        foreach ($diagnostics['errors'] as $error) {
            $this->printer->error('ERROR: ' . $error);
        }
    }

    /** @param array{notices: string[], errors: string[]} $diagnostics */
    protected function ensureCanContinue(
        array $diagnostics,
        string $src,
        string $dest,
        bool $yes,
        InputInterface $input,
        OutputInterface $output
    ): void {
        if ($diagnostics['errors'] !== []) {
            throw new \RuntimeException('Обнаружены конфликты. Загрузка остановлена.', static::CODE_IO_ERROR);
        }

        if ($diagnostics['notices'] === [] || $yes) {
            return;
        }

        $question = new ConfirmationQuestion(
            sprintf('Точно залить папку %s в папку %s? [y/N] ', $src, $dest),
            false,
        );
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $confirmed = $helper->ask($input, $output, $question);

        if ($confirmed !== true) {
            throw new \RuntimeException('Загрузка отменена пользователем.', static::CODE_SUCCESS);
        }
    }

    /** @param string[] $directories */
    protected function createLocalDirectories(string $documentRoot, string $dest, array $directories): int
    {
        $created = 0;

        foreach ($directories as $directory) {
            $target = $this->joinProjectPath($dest, $directory);
            $fullTarget = $documentRoot . $target;

            if (is_dir($fullTarget)) {
                continue;
            }

            $this->printer->info(sprintf('Создание директории: %s', $target));

            if (!mkdir($fullTarget, 0775, true) && !is_dir($fullTarget)) {
                throw new \RuntimeException(
                    sprintf('Не удалось создать директорию: %s', $target),
                    static::CODE_IO_ERROR,
                );
            }

            $created++;
        }

        return $created;
    }

    /** @param string[] $directories */
    protected function createRemoteDirectories(
        string $endpoint,
        string $sessionId,
        string $dest,
        array $directories
    ): int {
        $code = $this->remoteFileApplyPhpCodeBuilder->buildCreateDirectories($dest, $directories);
        $json = $this->bitrixAdminClient->executePhp($endpoint, $sessionId, $code);
        $result = json_decode($json, true);

        if (!is_array($result) || ($result['ok'] ?? false) !== true) {
            $error = is_array($result) && is_string($result['error'] ?? null)
                ? $result['error']
                : 'Не удалось создать директории удаленного проекта.';
            throw new \RuntimeException($error);
        }

        return (int) ($result['created'] ?? 0);
    }

    /**
     * @param array<int, array{relative: string, path: string, size: int}> $files
     * @return array{files: int, bytes: int}
     */
    protected function copyLocalFiles(string $documentRoot, string $dest, array $files, OutputInterface $output): array
    {
        $uploaded = 0;
        $bytes = 0;

        foreach ($files as $file) {
            $target = $this->joinProjectPath($dest, $file['relative']);
            $fullTarget = $documentRoot . $target;
            $this->printer->info(sprintf('Копирование файла: %s', $target));
            if (!copy($file['path'], $fullTarget)) {
                throw new \RuntimeException(sprintf('Не удалось скопировать файл: %s', $target), static::CODE_IO_ERROR);
            }

            $uploaded++;
            $bytes += $file['size'];
        }

        return ['files' => $uploaded, 'bytes' => $bytes];
    }

    /**
     * @param mixed[] $config
     * @param array<int, array{relative: string, path: string, size: int}> $files
     * @return array{files: int, bytes: int}
     */
    protected function uploadRemoteFiles(
        string $codename,
        array $config,
        string $endpoint,
        string $sessionId,
        string $dest,
        array $files,
        OutputInterface $output
    ): array {
        $uploaded = 0;
        $bytes = 0;

        foreach ($files as $file) {
            $target = $this->joinProjectPath($dest, $file['relative']);
            $directory = dirname($target);
            $filename = basename($target);
            $this->printer->info(sprintf('Загрузка файла: %s', $target));
            $this->withRemoteSessionRetry($codename, $config, $endpoint, $sessionId, function (
                string $sessionId
            ) use (
                $endpoint,
                $file,
                $directory,
                $filename
): void {
                $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $file['path'], $directory, $filename);
            });

            $uploaded++;
            $bytes += $file['size'];
        }

        return ['files' => $uploaded, 'bytes' => $bytes];
    }

    /** @param mixed[] $config */
    protected function withRemoteSessionRetry(
        string $codename,
        array $config,
        string $endpoint,
        string $sessionId,
        callable $callback
    ): mixed {
        try {
            return $callback($sessionId);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            return $callback($this->remoteProjectConfigManager->refreshSession($codename, $config));
        }
    }

    protected function joinProjectPath(string $dest, string $relative): string
    {
        $value = trim(trim($dest, '/') . '/' . trim($relative, '/'), '/');

        return '/' . $value;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['байт', 'КБ', 'МБ', 'ГБ'];
        $value = (float) $bytes;

        foreach ($units as $index => $unit) {
            if ($value < 1024 || $index === count($units) - 1) {
                return $index === 0
                    ? $bytes . ' ' . $unit
                    : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . ' ' . $unit;
            }

            $value /= 1024;
        }

        return $bytes . ' байт';
    }

    protected function printSummary(int $files, int $bytes, int $directories): void
    {
        $this->printer->info(sprintf(
            'Готово: загружено %d файлов общим размером %s (%d байт), создано %d папок.',
            $files,
            $this->formatBytes($bytes),
            $bytes,
            $directories,
        ));
    }
}
