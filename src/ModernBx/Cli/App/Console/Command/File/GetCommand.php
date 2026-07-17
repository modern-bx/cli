<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\File;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\ProjectRegistry;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetCommand extends BxCommand
{
    protected static $defaultName = 'file:get';

    private const PROGRESS_BAR_THRESHOLD = 1048576;

    protected ProjectRegistry $projectRegistry;

    protected BitrixAdminClient $bitrixAdminClient;

    public function __construct(ProjectRegistry $projectRegistry, BitrixAdminClient $bitrixAdminClient)
    {
        parent::__construct();

        $this->projectRegistry = $projectRegistry;
        $this->bitrixAdminClient = $bitrixAdminClient;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Скачивает файл из файловой структуры проекта')
            ->setHelp('Команда копирует файл относительно document root локального или удаленного проекта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addArgument('src', InputArgument::REQUIRED, 'Путь к файлу относительно document root проекта')
            ->addArgument('dest', InputArgument::REQUIRED, 'Локальный путь назначения');
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

        $src = $this->normalizeSourcePath($srcArgument);
        $dest = $this->resolveDestinationPath($destArgument);

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $this->executeRemote($remote, $src, $dest, $output);
            return;
        }

        parent::executeInternal($input, $output);
        $this->executeLocal($src, $dest);
    }

    protected function executeLocal(string $src, string $dest): void
    {
        $sourcePath = $this->resolveLocalSourcePath($src);
        $this->ensureDestinationDirectory($dest);

        if (!is_file($sourcePath)) {
            throw new \RuntimeException(sprintf('Файл не найден: %s', $src), static::CODE_IO_ERROR);
        }

        if (!copy($sourcePath, $dest)) {
            throw new \RuntimeException(sprintf('Не удалось скопировать файл в: %s', $dest), static::CODE_IO_ERROR);
        }

        $this->printer->info(sprintf('Файл сохранен: %s', $dest));
    }

    protected function executeRemote(string $codename, string $src, string $dest, OutputInterface $output): void
    {
        $config = $this->projectRegistry->load($codename);
        $project = $this->getProjectConfig($config);
        $account = $this->getDefaultAccountConfig($project);
        $endpoint = $this->readString($project, 'endpoint');
        $sessionId = $this->readString($this->getSessionCookieConfig($account), 'value');

        if ($sessionId === '') {
            $sessionId = $this->refreshRemoteSession($codename, $config, $project, $account);
        }

        $progressBar = null;
        $progressFactory = function (int $contentLength) use ($output, &$progressBar): ?callable {
            if ($contentLength <= self::PROGRESS_BAR_THRESHOLD) {
                return null;
            }

            $progressBar = new ProgressBar($output, $contentLength);
            $progressBar->setFormat(' %current%/%max% байт [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
            $progressBar->start();

            return static function (int $downloaded) use ($progressBar): void {
                $progressBar->setProgress($downloaded);
            };
        };

        $this->ensureDestinationDirectory($dest);

        try {
            $this->bitrixAdminClient->downloadFile($endpoint, $sessionId, $src, $dest, $progressFactory);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }

            $sessionId = $this->refreshRemoteSession($codename, $config, $project, $account);
            $this->bitrixAdminClient->downloadFile($endpoint, $sessionId, $src, $dest, $progressFactory);
        } finally {
            if ($progressBar !== null) {
                $progressBar->finish();
                $output->writeln('');
            }
        }

        $this->printer->info(sprintf('Файл сохранен: %s', $dest));
    }

    protected function normalizeSourcePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new \RuntimeException('Путь src не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $segments = [];
        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(
                    'Путь src не должен выходить за document root.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \RuntimeException('Путь src должен указывать на файл.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return '/' . implode('/', $segments);
    }

    protected function resolveDestinationPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new \RuntimeException('Путь dest не должен быть пустым.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if ($path[0] === '/') {
            return $path;
        }

        return getcwd() . '/' . $path;
    }

    protected function resolveLocalSourcePath(string $src): string
    {
        $documentRoot = rtrim((string) $this->getDocumentRoot(), '/');

        return $documentRoot . $src;
    }

    protected function ensureDestinationDirectory(string $dest): void
    {
        $dir = dirname($dest);

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(
                sprintf('Не удалось создать каталог назначения: %s', $dir),
                static::CODE_IO_ERROR,
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function getProjectConfig(array $config): array
    {
        $data = $config['data'] ?? null;
        $project = is_array($data) ? ($data['project'] ?? null) : null;

        if (!is_array($project)) {
            throw new \RuntimeException('Некорректная конфигурация удаленного проекта.');
        }

        return $project;
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    protected function getDefaultAccountConfig(array $project): array
    {
        $accounts = $project['accounts'] ?? null;
        $account = is_array($accounts) ? ($accounts['default'] ?? null) : null;

        if (!is_array($account)) {
            throw new \RuntimeException('В конфигурации удаленного проекта нет аккаунта default.');
        }

        return $account;
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    protected function getSessionCookieConfig(array $account): array
    {
        $cookies = $account['cookies'] ?? [];
        $cookie = is_array($cookies) ? ($cookies['PHPSESSID'] ?? []) : [];

        return is_array($cookie) ? $cookie : [];
    }

    /** @param array<string, mixed> $values */
    protected function readString(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $project
     * @param array<string, mixed> $account
     */
    protected function refreshRemoteSession(string $codename, array &$config, array $project, array $account): string
    {
        $endpoint = $this->readString($project, 'endpoint');
        $login = $this->readString($account, 'login');
        $password = $this->readString($account, 'password');

        if ($endpoint === '' || $login === '' || $password === '') {
            throw new \RuntimeException('Некорректные учетные данные удаленного проекта.');
        }

        $cookie = $this->bitrixAdminClient->login($endpoint, $login, $password);
        $this->writeSessionCookieConfig($config, $cookie);
        $this->projectRegistry->save($codename, $config);

        return $cookie['value'];
    }

    /**
     * @param array<string, mixed> $config
     * @param array{value: string, expires: string} $cookie
     */
    protected function writeSessionCookieConfig(array &$config, array $cookie): void
    {
        $data = is_array($config['data'] ?? null) ? $config['data'] : [];
        $project = is_array($data['project'] ?? null) ? $data['project'] : [];
        $accounts = is_array($project['accounts'] ?? null) ? $project['accounts'] : [];
        $default = is_array($accounts['default'] ?? null) ? $accounts['default'] : [];
        $cookies = is_array($default['cookies'] ?? null) ? $default['cookies'] : [];
        $cookies['PHPSESSID'] = $cookie;
        $default['cookies'] = $cookies;
        $accounts['default'] = $default;
        $project['accounts'] = $accounts;
        $data['project'] = $project;
        $config['data'] = $data;
    }
}
