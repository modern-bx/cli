<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteProjectConfigManager
{
    private ProjectRegistry $projectRegistry;

    private BitrixAdminClient $bitrixAdminClient;

    public function __construct(ProjectRegistry $projectRegistry, BitrixAdminClient $bitrixAdminClient)
    {
        $this->projectRegistry = $projectRegistry;
        $this->bitrixAdminClient = $bitrixAdminClient;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $codename): array
    {
        return $this->projectRegistry->load($codename);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function getEndpoint(array $config): string
    {
        return $this->readString($this->getProjectConfig($config), 'endpoint');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function getSessionId(array $config): string
    {
        return $this->readString($this->getSessionCookieConfig($this->getDefaultAccountConfig($config)), 'value');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function refreshSession(string $codename, array &$config): string
    {
        $project = $this->getProjectConfig($config);
        $account = $this->getDefaultAccountConfig($config);
        $endpoint = $this->readString($project, 'endpoint');
        $login = $this->readString($account, 'login');
        $password = $this->readString($account, 'password');

        if ($endpoint === '' || $login === '' || $password === '') {
            throw new \RuntimeException('Некорректные учетные данные удаленного проекта.');
        }

        $cookie = $this->bitrixAdminClient->login($endpoint, $login, $password);
        $config = $this->withSessionCookie($config, $cookie);
        $this->projectRegistry->save($codename, $config);

        return $cookie['value'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function getProjectConfig(array $config): array
    {
        $data = $config['data'] ?? null;
        $project = is_array($data) ? ($data['project'] ?? null) : null;

        if (!is_array($project)) {
            throw new \RuntimeException('Некорректная конфигурация удаленного проекта.');
        }

        return $project;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function getDefaultAccountConfig(array $config): array
    {
        $project = $this->getProjectConfig($config);
        $accounts = $project['accounts'] ?? null;
        $account = is_array($accounts) ? ($accounts['default'] ?? null) : null;

        if (!is_array($account)) {
            throw new \RuntimeException('В конфигурации удаленного проекта нет аккаунта default.');
        }

        return $account;
    }

    /**
     * @param array<string, mixed> $config
     * @param array{value: string, expires: string} $cookie
     * @return array<string, mixed>
     */
    public function withSessionCookie(array $config, array $cookie): array
    {
        return $this->mergeTree(
            $this->getEmptyConfigTree(),
            $config,
            [
                'data' => [
                    'project' => [
                        'accounts' => [
                            'default' => [
                                'cookies' => [
                                    'PHPSESSID' => $cookie,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEmptyConfigTree(): array
    {
        return [
            'version' => '',
            'data' => [
                'project' => [
                    'endpoint' => '',
                    'accounts' => [
                        'default' => [
                            'login' => '',
                            'password' => '',
                            'cookies' => [
                                'PHPSESSID' => [
                                    'value' => '',
                                    'expires' => '',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> ...$trees
     * @return array<string, mixed>
     */
    protected function mergeTree(array ...$trees): array
    {
        $result = [];

        foreach ($trees as $tree) {
            foreach ($tree as $key => $value) {
                if (is_array($value) && is_array($result[$key] ?? null)) {
                    /** @var array<string, mixed> $current */
                    $current = $result[$key];
                    /** @var array<string, mixed> $nested */
                    $nested = $value;
                    $result[$key] = $this->mergeTree($current, $nested);
                    continue;
                }

                $result[$key] = $value;
            }
        }

        return $result;
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

    /**
     * @param array<string, mixed> $values
     */
    protected function readString(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
