<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Db;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Console\Mixin\Bx\SettingFile;
use Symfony\Component\Console\Input\InputInterface;

class DbCommand extends BxCommand
{
    use SettingFile;

    /**
     * @param InputInterface $input
     * @return array<int, string>|null
     * @throws \Exception
     */
    protected function getTableFilter(InputInterface $input): ?array
    {
        $tables = $input->getOption('table');

        if ($tables === null) {
            return null;
        }

        if (!is_string($tables) || trim($tables) === '') {
            throw new \Exception($this->trans('error.db.table_string'), static::CODE_INVALID_OPTION_VALUE);
        }

        $filter = array_values(array_filter(
            array_map(static fn (string $table): string => trim($table), explode(',', $tables)),
            static fn (string $table): bool => $table !== ''
        ));

        if ($filter === []) {
            throw new \Exception($this->trans('error.db.table_string'), static::CODE_INVALID_OPTION_VALUE);
        }

        return $filter;
    }

    /**
     * @return array<string, mixed>
     * @throws \Exception
     */
    protected function getConnectionConfig(): array
    {
        $settingsFile = $this->getSettingsFile(false);

        if (file_exists($settingsFile)) {
            $settings = $this->loadSettings($settingsFile);
            $connection = $this->getDefaultConnection($settings);

            if (is_array($connection)) {
                $className = (string) ($connection['className'] ?? '');

                $type = $this->detectType($connection, $className);

                return [
                    'type' => $type,
                    'host' => $connection['host'] ?? 'localhost',
                    'port' => $connection['port'] ?? 3306,
                    'socket' => $connection['socket'] ?? '',
                    'database' => $connection['database'] ?? '',
                    'login' => $connection['login'] ?? '',
                    'password' => $connection['password'] ?? '',
                    'charset' => $connection['charset'] ?? 'utf8mb4',
                ];
            }
        }

        return $this->getLegacyConnectionConfig();
    }

    /**
     * @param array<string, mixed> $connection
     * @param string $className
     * @return string
     * @throws \Exception
     */
    private function detectType(array $connection, string $className): string
    {
        $rawType = $connection['type'] ?? $className;
        $type = mb_strtolower(is_scalar($rawType) ? (string) $rawType : $className);

        if (str_contains($type, 'pgsql') || str_contains($type, 'postgres')) {
            return 'postgres';
        }

        if ($type === '' || str_contains($type, 'mysql')) {
            return 'mysql';
        }

        throw new \Exception('Only MySQL and PostgreSQL connections are supported by SQL commands now.');
    }

    /**
     * @param array<string, mixed> $settings
     * @return mixed
     */
    private function getDefaultConnection(array $settings): mixed
    {
        $connections = $settings['connections'] ?? null;

        if (!is_array($connections)) {
            return null;
        }

        $connectionsValue = $connections['value'] ?? null;

        if (!is_array($connectionsValue)) {
            return null;
        }

        return $connectionsValue['default'] ?? null;
    }

    /**
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function getLegacyConnectionConfig(): array
    {
        $file = $this->bxRoot->toString() . 'php_interface/dbconn.php';

        if (!file_exists($file)) {
            throw new \Exception('Bitrix database connection settings have not been found.');
        }

        $DBHost = $DBLogin = $DBPassword = $DBName = '';
        require $file;

        return [
            'type' => 'mysql',
            'host' => $DBHost,
            'database' => $DBName,
            'login' => $DBLogin,
            'password' => $DBPassword,
            'charset' => defined('BX_UTF') && BX_UTF === true ? 'utf8mb4' : 'cp1251',
        ];
    }
}
