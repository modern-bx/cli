<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Project;

use ModernBx\Cli\App\Console\Command\AppCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Yaml\Yaml;

class RegisterCommand extends AppCommand
{
    protected static $defaultName = 'project:register';

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
                ]),
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        /** @var string $endpoint */
        $endpoint = $input->getArgument('endpoint');
        $endpoint = $this->normalizeEndpoint($endpoint);
        $projectName = $this->makeProjectName($endpoint);
        $configFile = $this->getProjectConfigFile($projectName);

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

        $sessionId = $this->login($endpoint, $login, $password);
        $this->saveProjectConfig($configFile, $projectName, $endpoint, $login, $password, $sessionId);

        $this->printer->info(sprintf('Проект зарегистрирован: %s', $configFile));
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

    protected function makeProjectName(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $name = strtolower((string) ($parts['host'] ?? 'project'));

        if (isset($parts['port'])) {
            $name .= '-' . $parts['port'];
        }

        $name = (string) preg_replace('/[^a-z0-9._-]+/', '-', $name);
        $name = trim($name, '.-_');

        return $name !== '' ? $name : 'project';
    }

    protected function getProjectConfigFile(string $projectName): string
    {
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? null);

        if (!$home) {
            throw new \RuntimeException('Не удалось определить домашний каталог пользователя.', static::CODE_IO_ERROR);
        }

        return rtrim($home, '/') . '/.config/bx-cli/projects/' . $projectName . '/project.yaml';
    }

    protected function login(string $endpoint, string $login, string $password): string
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
            'User-Agent: bx-cli project:register',
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

        $sessionId = $this->extractPhpSessionId($responseHeaders);

        if ($sessionId === null) {
            throw new \RuntimeException('Авторизация не вернула PHPSESSID.', static::CODE_IO_ERROR);
        }

        if ($statusCode < 300 && $this->looksLikeLoginForm($response)) {
            throw new \RuntimeException('Неверный логин или пароль администратора.', static::CODE_IO_ERROR);
        }

        return $sessionId;
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
     */
    protected function extractPhpSessionId(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]+)/i', $header, $matches)) {
                return urldecode($matches[1]);
            }
        }

        return null;
    }

    protected function saveProjectConfig(
        string $configFile,
        string $projectName,
        string $endpoint,
        string $login,
        string $password,
        string $sessionId
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
                                'PHPSESSID' => $sessionId,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $content = Yaml::dump($config, 6, 2);

        if (file_put_contents($configFile, $content) === false) {
            throw new \RuntimeException(
                sprintf('Не удалось сохранить конфигурацию: %s', $configFile),
                static::CODE_IO_ERROR,
            );
        }

        chmod($configFile, 0600);
    }
}
