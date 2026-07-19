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

        $errors = [];

        foreach ($directories as $directory) {
            $path = $this->bxRoot->pushPathSegment($directory)->toString();

            if (file_exists($path) && is_dir($path)) {
                array_push($errors, ...$this->collectDeleteDirectoryContentErrors($path));
            }
        }

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

        if (($result['ok'] ?? false) === true) {
            return;
        }

        $error = $result['error'] ?? 'Не удалось очистить кеш удаленного проекта.';

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->throwIfDeleteErrors($result['errors']);
        }

        throw new \RuntimeException(is_string($error) ? $error : 'Не удалось очистить кеш удаленного проекта.');
    }

    /** @param array<int, mixed> $errors */
    protected function throwIfDeleteErrors(array $errors): void
    {
        if ($errors === []) {
            return;
        }

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
