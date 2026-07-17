<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Sql;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Console\Mixin\Bx\SettingFile;

class SqlCommand extends BxCommand
{
    use SettingFile;

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

                if ($className !== '' && !str_contains(mb_strtolower($className), 'mysql')) {
                    throw new \Exception('Only MySQL connections are supported by SQL commands now.');
                }

                return [
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
            'host' => $DBHost,
            'database' => $DBName,
            'login' => $DBLogin,
            'password' => $DBPassword,
            'charset' => defined('BX_UTF') && BX_UTF === true ? 'utf8mb4' : 'cp1251',
        ];
    }
}
