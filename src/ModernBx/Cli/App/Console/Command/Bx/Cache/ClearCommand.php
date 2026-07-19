<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Cache;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteCachePhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCommand extends BxCommand
{
    use IO;
    use RemotePhpTrait;

    private RemoteCachePhpCodeBuilder $remoteCachePhpCodeBuilder;

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteCachePhpCodeBuilder $remoteCachePhpCodeBuilder
    ) {
        parent::__construct();

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteCachePhpCodeBuilder = $remoteCachePhpCodeBuilder;
    }

    /**
     * @var string
     */
    protected static $defaultName = 'cache:clear';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.cache_clear.description"))
            ->setHelp($this->trans("command.cache_clear.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputOption(
                        'remote',
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Кодовое имя удаленного проекта',
                    ),
                    new InputOption(
                        'local',
                        null,
                        InputOption::VALUE_NONE,
                        'Отключить неявный remote текущей сессии',
                    ),
                    new InputArgument(
                        'directory',
                        InputArgument::IS_ARRAY,
                        $this->trans("argument.cache.directory"),
                    ),
                ])
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $directories = $this->resolveDirectories($input);
        $remote = $input->getOption('remote');

        if (is_string($remote)) {
            $this->executeRemote($remote, $directories);
            return;
        }

        parent::executeInternal($input, $output);

        $stats = [];
        $errors = [];

        foreach ($directories as $directory) {
            $stats[$directory] = $this->createEmptyStats($directory);
            $path = $this->bxRoot->pushPathSegment($directory)->toString();

            if (file_exists($path) && is_dir($path)) {
                $this->clearDirectoryContent($path, $directory, $stats[$directory], $errors);
            }
        }

        $this->printStats($stats);
        $this->throwIfDeleteErrors($errors);
    }

    /** @param string[] $directories */
    protected function executeRemote(string $remote, array $directories): void
    {
        $json = $this->executeRemotePhp($remote, $this->remoteCachePhpCodeBuilder->build($directories));
        $result = json_decode($json, true);

        if (!is_array($result)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный JSON.');
        }

        if (isset($result['result']) && is_array($result['result'])) {
            $this->printStats($result['result']);
        }

        if (($result['ok'] ?? false) === true) {
            return;
        }

        $error = $result['error'] ?? 'Не удалось очистить кеш удаленного проекта.';

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->throwIfDeleteErrors($result['errors']);
        }

        throw new \RuntimeException(is_string($error) ? $error : 'Не удалось очистить кеш удаленного проекта.');
    }

    /**
     * @param array{directory: string, deleted_files: int, freed_bytes: int, errors: int} $stats
     * @param array<int, array{path: string, reason: string|null, directory: string}> $errors
     */
    protected function clearDirectoryContent(string $path, string $directory, array &$stats, array &$errors): void
    {
        $items = @scandir($path);

        if ($items === false) {
            $this->addDeleteError($errors, $stats, $path, $this->getLastPhpErrorMessage(), $directory);
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->deletePath($path . DIRECTORY_SEPARATOR . $item, $directory, $stats, $errors);
        }
    }

    /**
     * @param array{directory: string, deleted_files: int, freed_bytes: int, errors: int} $stats
     * @param array<int, array{path: string, reason: string|null, directory: string}> $errors
     */
    protected function deletePath(string $path, string $directory, array &$stats, array &$errors): void
    {
        if (is_link($path) || is_file($path)) {
            if (!file_exists($path) && !is_link($path)) {
                return;
            }

            $size = @filesize($path);

            if (@unlink($path)) {
                $stats['deleted_files']++;
                $stats['freed_bytes'] += is_int($size) ? $size : 0;
                return;
            }

            $this->addDeleteError($errors, $stats, $path, $this->getLastPhpErrorMessage(), $directory);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $this->clearDirectoryContent($path, $directory, $stats, $errors);

        if (!@rmdir($path)) {
            $this->addDeleteError($errors, $stats, $path, $this->getLastPhpErrorMessage(), $directory);
        }
    }

    /** @return array{directory: string, deleted_files: int, freed_bytes: int, errors: int} */
    protected function createEmptyStats(string $directory): array
    {
        return [
            'directory' => $directory,
            'deleted_files' => 0,
            'freed_bytes' => 0,
            'errors' => 0,
        ];
    }

    /**
     * @param array<int, array{path: string, reason: string|null, directory: string}> $errors
     * @param array{directory: string, deleted_files: int, freed_bytes: int, errors: int} $stats
     */
    protected function addDeleteError(
        array &$errors,
        array &$stats,
        string $path,
        ?string $reason,
        string $directory
    ): void {
        $stats['errors']++;
        $errors[] = [
            'path' => $path,
            'reason' => $reason,
            'directory' => $directory,
        ];
    }

    /** @param array<string, mixed> $stats */
    protected function printStats(array $stats): void
    {
        foreach ($stats as $directory => $item) {
            if (!is_array($item)) {
                $item = [];
            }

            $name = isset($item['directory']) && is_string($item['directory'])
                ? $item['directory']
                : (string) $directory;
            $deletedFiles = isset($item['deleted_files']) && is_numeric($item['deleted_files'])
                ? (int) $item['deleted_files']
                : 0;
            $freedBytes = isset($item['freed_bytes']) && is_numeric($item['freed_bytes'])
                ? (int) $item['freed_bytes']
                : 0;
            $errors = isset($item['errors']) && is_numeric($item['errors']) ? (int) $item['errors'] : 0;

            $this->printer->info(sprintf(
                '%s: удалено файлов: %d, освобождено: %s, ошибок: %d',
                $name,
                $deletedFiles,
                $this->formatBytes($freedBytes),
                $errors,
            ));
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $mb = $bytes / 1024 / 1024;

        if ($mb >= 1024) {
            return sprintf('%.2f Гб', $mb / 1024);
        }

        return sprintf('%.2f Мб', $mb);
    }

    protected function getLastPhpErrorMessage(): ?string
    {
        $error = error_get_last();

        return is_array($error) ? (string) $error['message'] : null;
    }

    /** @param array<int, mixed> $errors */
    protected function throwIfDeleteErrors(array $errors): void
    {
        if ($errors === []) {
            return;
        }

        if ($this->isVerbose()) {
            foreach ($errors as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $path = isset($item['path']) && is_string($item['path']) ? $item['path'] : 'unknown';
                $reason = isset($item['reason']) && is_string($item['reason']) && $item['reason'] !== ''
                    ? $item['reason']
                    : 'причина неизвестна';

                $this->printer->error(sprintf('Ошибка удаления (%s): %s', $reason, $path));
            }
        }

        throw new \RuntimeException('Не удалось удалить часть файлов кеша.', static::CODE_IO_ERROR);
    }

    /** @return string[] */
    protected function resolveDirectories(InputInterface $input): array
    {
        /** @var array<string> $directories */
        $directories = $input->getArgument("directory") ?: $this->getDefaultDirectories();

        foreach ($directories as $directory) {
            if (!in_array($directory, $this->getValidDirectories(), true)) {
                throw new \Exception(
                    $this->trans("error.cache.invalid_directory"),
                    static::CODE_INVALID_ARGUMENT_VALUE
                );
            }
        }

        return $directories;
    }

    /**
     * @return string[]
     */
    protected function getDefaultDirectories(): array
    {
        return [
            "cache",
            "managed_cache",
            "stack_cache",
        ];
    }

    /**
     * @return string[]
     */
    protected function getValidDirectories(): array
    {
        return [
            "cache",
            "managed_cache",
            "stack_cache",
        ];
    }
}
