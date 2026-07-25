<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Vendor;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class InstallCommand extends BxCommand
{
    protected static $defaultName = 'vendor:install';

    private const ADMINER_URL = 'https://www.adminer.org/latest.php';
    private const ADMINER_FILENAME = 'adminer.php';
    private const CACHE_DIRECTORY = '.config/bx-cli/cache/vendor-install/adminer';

    private RemoteProjectConfigManager $remoteProjectConfigManager;
    private BitrixAdminClient $bitrixAdminClient;

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
            ->setDescription('Устанавливает стороннее ПО в корень сайта.')
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addArgument('package', InputArgument::REQUIRED, 'Пакет для установки: adminer');
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $package = $input->getArgument('package');
        if (!is_string($package) || strtolower($package) !== 'adminer') {
            throw new \RuntimeException('Неизвестный пакет. Доступно: adminer.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $remote = $input->getOption('remote');
        $credentials = $this->installAdminer(is_string($remote) ? $remote : null, $input, $output);

        $this->printer->info('Adminer установлен: /' . self::ADMINER_FILENAME);
        $this->printer->info('Логин: ' . $credentials['login']);
        $this->printer->info('Пароль: ' . $credentials['password']);
    }

    /** @return array{login: string, password: string} */
    private function installAdminer(?string $remote, InputInterface $input, OutputInterface $output): array
    {
        $cache = $this->cachePath(self::ADMINER_FILENAME);
        $meta = $this->head(self::ADMINER_URL);

        if (!$this->cacheIsFresh($cache, $meta)) {
            $this->printer->info('Скачиваю adminer: ' . self::ADMINER_URL);
            $this->download(self::ADMINER_URL, $cache);
            $this->writeMeta($cache, $meta);
        } else {
            $this->printer->info('Использую кешированный adminer: ' . $cache);
        }

        $password = $this->generatePassword();
        $prepared = $this->prepareAdminer($cache, 'admin', $password);

        if ($remote !== null) {
            $this->uploadRemote($remote, $prepared);
        } else {
            parent::executeInternal($input, $output);
            $target = rtrim($this->getDocumentRoot()->toString(), '/') . '/' . self::ADMINER_FILENAME;
            if (file_put_contents($target, file_get_contents($prepared)) === false) {
                throw new \RuntimeException('Не удалось записать файл: ' . $target, static::CODE_IO_ERROR);
            }
        }

        @unlink($prepared);
        return ['login' => 'admin', 'password' => $password];
    }

    private function uploadRemote(string $codename, string $source): void
    {
        $config = $this->remoteProjectConfigManager->load($codename);
        $endpoint = $this->remoteProjectConfigManager->getEndpoint($config);
        $sessionId = $this->remoteProjectConfigManager->getSessionId($config);
        if ($sessionId === '') {
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
        }

        try {
            $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, '/' . self::ADMINER_FILENAME);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() === 'REMOTE_SESSION_EXPIRED') {
                $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
                $this->bitrixAdminClient->deleteFile($endpoint, $sessionId, '/' . self::ADMINER_FILENAME);
            }
        }

        try {
            $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $source, '/', self::ADMINER_FILENAME);
        } catch (\RuntimeException $err) {
            if ($err->getMessage() !== 'REMOTE_SESSION_EXPIRED') {
                throw $err;
            }
            $sessionId = $this->remoteProjectConfigManager->refreshSession($codename, $config);
            $this->bitrixAdminClient->uploadFile($endpoint, $sessionId, $source, '/', self::ADMINER_FILENAME);
        }
    }

    /** @param array{length: int|null, etag: string|null, last_modified: string|null} $meta */
    private function cacheIsFresh(string $cache, array $meta): bool
    {
        if (!is_file($cache)) {
            return false;
        }
        $stored = json_decode((string) @file_get_contents($cache . '.json'), true);
        return is_array($stored) && $stored == $meta;
    }

    private function cachePath(string $filename): string
    {
        $home = getenv('HOME');
        if (!is_string($home) || $home === '') {
            throw new \RuntimeException('Не удалось определить домашнюю директорию для кеша.');
        }
        return implode(DIRECTORY_SEPARATOR, [
            rtrim($home, DIRECTORY_SEPARATOR),
            self::CACHE_DIRECTORY,
            $filename,
        ]);
    }

    /** @return array{length: int|null, etag: string|null, last_modified: string|null} */
    private function head(string $url): array
    {
        $headers = @get_headers($url, true, stream_context_create([
            'http' => ['method' => 'HEAD', 'user_agent' => 'bx-cli'],
        ]));
        if (!is_array($headers)) {
            return ['length' => null, 'etag' => null, 'last_modified' => null];
        }
        $status = $headers[0] ?? '';
        if (is_string($status) && preg_match('/^HTTP\/\S+\s+(\d+)/', $status, $m) && (int) $m[1] >= 400) {
            throw new \RuntimeException(sprintf('Сервер вернул ошибку %s для %s', $m[1], $url));
        }
        return [
            'length' => $this->headerInt($headers, 'Content-Length'),
            'etag' => $this->headerString($headers, 'ETag'),
            'last_modified' => $this->headerString($headers, 'Last-Modified'),
        ];
    }

    /** @param array<string|int, mixed> $headers */
    private function headerString(array $headers, string $name): ?string
    {
        $value = $headers[$name] ?? $headers[strtolower($name)] ?? null;
        if (is_array($value)) {
            $value = end($value);
        }
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string|int, mixed> $headers */
    private function headerInt(array $headers, string $name): ?int
    {
        $value = $this->headerString($headers, $name);
        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    /** @param array{length: int|null, etag: string|null, last_modified: string|null} $meta */
    private function writeMeta(string $cache, array $meta): void
    {
        file_put_contents($cache . '.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function download(string $url, string $target): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Не удалось создать директорию кеша: ' . $directory);
        }
        $content = @file_get_contents($url, false, stream_context_create(['http' => ['user_agent' => 'bx-cli']]));
        if (!is_string($content)) {
            throw new \RuntimeException('Не удалось скачать файл: ' . $url);
        }
        file_put_contents($target, $content);
    }

    private function prepareAdminer(string $source, string $login, string $password): string
    {
        $content = file_get_contents($source);
        if (!is_string($content)) {
            throw new \RuntimeException('Не удалось прочитать файл: ' . $source);
        }
        $snippet = file_get_contents($this->authSnippetPath());
        if (!is_string($snippet)) {
            throw new \RuntimeException('Не удалось прочитать сниппет авторизации.');
        }
        $snippet = preg_replace('/^<\?php\s*/', '', $snippet, 1);
        if (!is_string($snippet)) {
            throw new \RuntimeException('Не удалось подготовить сниппет авторизации.');
        }
        $snippet = str_replace(
            ['__BX_CLI_ADMINER_LOGIN__', '__BX_CLI_ADMINER_PASSWORD_HASH__'],
            [$login, password_hash($password, PASSWORD_DEFAULT)],
            $snippet,
        );
        $content = preg_replace('/^<\?php\s*/', "<?php\n" . $snippet . "\n", $content, 1);
        if (!is_string($content)) {
            throw new \RuntimeException('Не удалось подшить авторизацию к adminer.');
        }
        $target = tempnam(sys_get_temp_dir(), 'bx-cli-adminer-');
        if (!is_string($target) || file_put_contents($target, $content) === false) {
            throw new \RuntimeException('Не удалось подготовить временный adminer.php.');
        }
        return $target;
    }

    private function authSnippetPath(): string
    {
        return dirname(__DIR__, 3) . '/Resources/Snippets/Vendor/adminer_http_auth.php';
    }

    private function generatePassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }
}
