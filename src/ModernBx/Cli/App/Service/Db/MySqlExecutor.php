<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Db;

final class MySqlExecutor
{
    /**
     * @param array<string, mixed> $config
     * @param string $sql
     * @return void
     * @throws \Exception
     */
    public function execute(array $config, string $sql): void
    {
        $connection = $this->connect($config);

        try {
            $this->executeMultiQuery($connection, $sql);
        } finally {
            $connection->close();
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param string $inputFile
     * @return void
     * @throws \Exception
     */
    public function apply(array $config, string $inputFile): void
    {
        if (!is_file($inputFile) || !is_readable($inputFile)) {
            throw new \Exception('SQL file is not readable: ' . $inputFile);
        }

        $sql = file_get_contents($inputFile);

        if ($sql === false) {
            throw new \Exception('Unable to read SQL file: ' . $inputFile);
        }

        $connection = $this->connect($config);

        try {
            $this->executeMultiQuery($connection, $sql);
        } finally {
            $connection->close();
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, string>|null $tables
     * @return int
     * @throws \Exception
     */
    public function wipe(array $config, ?array $tables = null): int
    {
        $connection = $this->connect($config);

        try {
            $tables = $this->getTables($connection, $tables);
            $this->executeQuery($connection, 'SET FOREIGN_KEY_CHECKS=0');

            try {
                foreach ($tables as $table) {
                    $this->executeQuery($connection, 'TRUNCATE TABLE ' . $this->quoteIdentifier($table));
                }
            } finally {
                $this->executeQuery($connection, 'SET FOREIGN_KEY_CHECKS=1');
            }

            return count($tables);
        } finally {
            $connection->close();
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return \mysqli
     * @throws \Exception
     */
    private function connect(array $config): \mysqli
    {
        if (!class_exists(\mysqli::class)) {
            throw new \Exception('PHP mysqli extension is required for MySQL commands.');
        }

        $host = $this->getStringConfig($config, 'host', 'localhost');
        $port = $this->getIntConfig($config, 'port', 3306);
        $socket = $this->getStringConfig($config, 'socket', '');
        $database = $this->getStringConfig($config, 'database', '');
        $login = $this->getStringConfig($config, 'login', '');
        $password = $this->getStringConfig($config, 'password', '');

        $connection = mysqli_init();

        if (!$connection) {
            throw new \Exception('Unable to initialize mysqli connection.');
        }

        if (!$connection->real_connect($host, $login, $password, $database, $port, $socket ?: null)) {
            throw new \Exception('Unable to connect to MySQL: ' . mysqli_connect_error());
        }

        $connection->set_charset($this->getStringConfig($config, 'charset', 'utf8mb4'));
        $this->executeQuery($connection, "SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
        $this->executeQuery($connection, 'SET SESSION time_zone = \'+00:00\'');

        return $connection;
    }

    /**
     * @param \mysqli $connection
     * @param string $sql
     * @return void
     * @throws \Exception
     */
    private function executeMultiQuery(\mysqli $connection, string $sql): void
    {
        if (trim($sql) === '') {
            return;
        }

        if (!$connection->multi_query($sql)) {
            throw new \Exception('Unable to execute SQL file: ' . $connection->error);
        }

        do {
            $result = $connection->store_result();

            if ($result instanceof \mysqli_result) {
                $result->free();
            }

            if ($connection->errno) {
                throw new \Exception('Unable to execute SQL statement: ' . $connection->error);
            }
        } while ($connection->more_results() && $connection->next_result());
    }

    /**
     * @param \mysqli $connection
     * @param array<int, string>|null $filter
     * @return array<int, string>
     * @throws \Exception
     */
    private function getTables(\mysqli $connection, ?array $filter = null): array
    {
        $result = $connection->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');

        if (!$result instanceof \mysqli_result) {
            throw new \Exception('Unable to fetch table list: ' . $connection->error);
        }

        $tables = [];

        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $tables[] = (string) $row[0];
        }

        $result->free();

        if ($filter !== null) {
            $allowed = array_flip($filter);
            $tables = array_values(array_filter(
                $tables,
                static fn (string $table): bool => array_key_exists($table, $allowed)
            ));
        }

        return $tables;
    }

    /**
     * @param \mysqli $connection
     * @param string $sql
     * @return void
     * @throws \Exception
     */
    private function executeQuery(\mysqli $connection, string $sql): void
    {
        if (!$connection->query($sql)) {
            throw new \Exception('Unable to execute SQL statement: ' . $connection->error);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param string $key
     * @param string $default
     * @return string
     */
    private function getStringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<string, mixed> $config
     * @param string $key
     * @param int $default
     * @return int
     */
    private function getIntConfig(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;

        return is_scalar($value) ? (int) $value : $default;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
