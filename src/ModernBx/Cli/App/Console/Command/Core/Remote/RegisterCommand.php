<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\ProjectNameGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterCommand extends AppCommand
{
    protected static $defaultName = 'remote:register';

    protected ProjectNameGenerator $projectNameGenerator;

    public function __construct(ProjectNameGenerator $projectNameGenerator, ?TranslatorInterface $translator = null)
    {
        $this->projectNameGenerator = $projectNameGenerator;

        parent::__construct($translator);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Регистрирует проект для удаленного управления')
            ->setHelp('Команда авторизуется в админке проекта и сохраняет endpoint, учетные данные и PHPSESSID.')
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'endpoint',
                        InputArgument::REQUIRED,
                        'Endpoint проекта в формате http(s)://host.tld[:port]',
                    ),
                    new InputArgument(
                        'codename',
                        InputArgument::OPTIONAL,
                        'Кодовое имя проекта',
                    ),
                ]),
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        /** @var string $endpoint */
        $endpoint = $input->getArgument('endpoint');
        $endpoint = $this->normalizeEndpoint($endpoint);
        $projectsDir = $this->getProjectsDir();
        $projectName = $this->resolveProjectName($input, $projectsDir, $endpoint);
        $configFile = $this->getProjectConfigFile($projectsDir, $projectName);

        if (is_file($configFile)) {
            $this->printer->info(sprintf('Предупреждение: конфигурация проекта уже существует: %s', $configFile));
            return;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $loginQuestion = new Question('Логин администратора: ');
        /** @var string|null $login */
        $login = $helper->ask($input, $output, $loginQuestion);
        $login = trim((string) $login);

        $passwordQuestion = new Question('Пароль администратора: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        /** @var string|null $password */
        $password = $helper->ask($input, $output, $passwordQuestion);
        $password = (string) $password;

        if ($login === '' || $password === '') {
            throw new \RuntimeException('Логин и пароль обязательны.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $sessionCookie = $this->login($endpoint, $login, $password);
        $this->saveProjectConfig($configFile, $projectName, $endpoint, $login, $password, $sessionCookie);

        $this->printer->info(sprintf('Проект зарегистрирован: %s', $projectName));
    }

    protected function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = rtrim(trim($endpoint), '/');
        $parts = parse_url($endpoint);

        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array($parts['scheme'], ['http', 'https'], true)
            || isset($parts['path'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \RuntimeException(
                'Endpoint должен быть в формате http(s)://host.tld[:port].',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        $normalized = $parts['scheme'] . '://' . strtolower($parts['host']);

        if (isset($parts['port'])) {
            $normalized .= ':' . $parts['port'];
        }

        return $normalized;
    }

    protected function resolveProjectName(InputInterface $input, string $projectsDir, string $endpoint): string
    {
        /** @var string|null $codename */
        $codename = $input->getArgument('codename');

        if ($codename !== null) {
            $codename = trim($codename);

            if (!$this->isValidProjectName($codename)) {
                throw new \RuntimeException(
                    'Кодовое имя проекта должно содержать только латинские буквы, цифры, точки, дефисы '
                    . 'и подчеркивания.',
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }

            return $codename;
        }

        $host = parse_url($endpoint, PHP_URL_HOST);

        if (is_string($host)) {
            $host = strtolower($host);

            if ($this->isValidProjectName($host) && !$this->projectConfigExists($projectsDir, $host)) {
                return $host;
            }
        }

        return $this->generateProjectName($projectsDir);
    }

    protected function isValidProjectName(string $projectName): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9._-]*$/', $projectName);
    }

    protected function generateProjectName(string $projectsDir): string
    {
        $projectName = $this->projectNameGenerator->generate(
            fn (string $name): bool => $this->projectConfigExists($projectsDir, $name),
        );

        if ($projectName !== null) {
            return $projectName;
        }

        throw new \RuntimeException(
            'Не удалось подобрать свободное кодовое имя проекта: все варианты заняты.',
            static::CODE_IO_ERROR,
        );
    }

    protected function getProjectsDir(): string
    {
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? null);

        if (!$home) {
            throw new \RuntimeException('Не удалось определить домашний каталог пользователя.', static::CODE_IO_ERROR);
        }

        return rtrim($home, '/') . '/.config/bx-cli/projects';
    }

    protected function getProjectConfigFile(string $projectsDir, string $projectName): string
    {
        return rtrim($projectsDir, '/') . '/' . $projectName . '/project.yaml';
    }

    protected function projectConfigExists(string $projectsDir, string $projectName): bool
    {
        return is_file($this->getProjectConfigFile($projectsDir, $projectName));
    }

    /**
     * @return array{value: string, expires: string}
     */
    protected function login(string $endpoint, string $login, string $password): array
    {
        $url = $endpoint . '/bitrix/admin/';
        $body = http_build_query([
            'AUTH_FORM' => 'Y',
            'TYPE' => 'AUTH',
            'backurl' => '/bitrix/admin/',
            'USER_LOGIN' => $login,
            'USER_PASSWORD' => $password,
            'Login' => 'Y',
        ]);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($body),
            'User-Agent: bx-cli remote:register',
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header;

        $statusCode = $this->getStatusCode($responseHeaders);

        if ($response === false || $statusCode === null || $statusCode < 200 || $statusCode >= 400) {
            throw new \RuntimeException('Не удалось авторизоваться в админке проекта.', static::CODE_IO_ERROR);
        }

        $sessionCookie = $this->extractPhpSessionIdCookie($responseHeaders);

        if ($sessionCookie === null) {
            throw new \RuntimeException('Авторизация не вернула PHPSESSID.', static::CODE_IO_ERROR);
        }

        if ($statusCode < 300 && $this->looksLikeLoginForm($response)) {
            throw new \RuntimeException('Неверный логин или пароль администратора.', static::CODE_IO_ERROR);
        }

        return $sessionCookie;
    }

    /**
     * @param string[] $headers
     */
    protected function getStatusCode(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    protected function looksLikeLoginForm(string $response): bool
    {
        return str_contains($response, 'name="AUTH_FORM"')
            || str_contains($response, "name='AUTH_FORM'")
            || str_contains($response, 'name="USER_LOGIN"')
            || str_contains($response, "name='USER_LOGIN'");
    }

    /**
     * @param string[] $headers
     * @return array{value: string, expires: string}|null
     */
    protected function extractPhpSessionIdCookie(array $headers): ?array
    {
        foreach ($headers as $header) {
            if (!preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]+)/i', $header, $matches)) {
                continue;
            }

            return [
                'value' => urldecode($matches[1]),
                'expires' => $this->extractCookieExpires($header),
            ];
        }

        return null;
    }

    protected function extractCookieExpires(string $setCookieHeader): string
    {
        if (preg_match('/;\s*expires=([^;]+)/i', $setCookieHeader, $matches)) {
            $timestamp = strtotime($matches[1]);

            if ($timestamp !== false) {
                return date(DATE_ATOM, $timestamp);
            }
        }

        if (preg_match('/;\s*max-age=(\d+)/i', $setCookieHeader, $matches)) {
            return date(DATE_ATOM, time() + (int) $matches[1]);
        }

        return date(DATE_ATOM, time() + (int) ini_get('session.gc_maxlifetime'));
    }

    /**
     * @param array{value: string, expires: string} $sessionCookie
     */
    protected function saveProjectConfig(
        string $configFile,
        string $projectName,
        string $endpoint,
        string $login,
        string $password,
        array $sessionCookie
    ): void {
        $dir = dirname($configFile);

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException(
                sprintf('Не удалось создать каталог конфигурации: %s', $dir),
                static::CODE_IO_ERROR,
            );
        }

        $config = [
            'meta' => [
                'schema' => 'project',
                'version' => 0.1,
            ],
            'data' => [
                'project' => [
                    'name' => $projectName,
                    'framework' => 'bitrix',
                    'language' => 'php',
                    'endpoint' => $endpoint,
                    'accounts' => [
                        'default' => [
                            'login' => $login,
                            'password' => $password,
                            'cookies' => [
                                'PHPSESSID' => $sessionCookie,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $content = Yaml::dump($config, 8, 2);

        if (file_put_contents($configFile, $content) === false) {
            throw new \RuntimeException(
                sprintf('Не удалось сохранить конфигурацию: %s', $configFile),
                static::CODE_IO_ERROR,
            );
        }

        chmod($configFile, 0600);
    }
}
