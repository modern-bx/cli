<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Db;

final class PgSqlExecutor
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
            if (trim($sql) !== '' && pg_query($connection, $sql) === false) {
                throw new \Exception('Unable to execute PostgreSQL query: ' . pg_last_error($connection));
            }
        } finally {
            pg_close($connection);
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
            if (trim($sql) !== '' && pg_query($connection, $sql) === false) {
                throw new \Exception('Unable to execute PostgreSQL file: ' . pg_last_error($connection));
            }
        } finally {
            pg_close($connection);
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
            $tables = $this->filterTables($this->getTables($connection), $tables);

            if ($tables === []) {
                return 0;
            }

            $quotedTables = array_map(
                fn (array $table): string => $this->quoteIdentifier($table['schema']) . '.' .
                    $this->quoteIdentifier($table['name']),
                $tables
            );
            $sql = 'TRUNCATE TABLE ' . implode(', ', $quotedTables) . ' RESTART IDENTITY CASCADE';

            if (pg_query($connection, $sql) === false) {
                throw new \Exception('Unable to truncate PostgreSQL tables: ' . pg_last_error($connection));
            }

            return count($tables);
        } finally {
            pg_close($connection);
        }
    }

    /**
     * @param array<int, array{schema: string, name: string}> $tables
     * @param array<int, string>|null $filter
     * @return array<int, array{schema: string, name: string}>
     */
    public function filterTables(array $tables, ?array $filter): array
    {
        if ($filter === null) {
            return $tables;
        }

        $allowed = array_flip($filter);

        return array_values(array_filter(
            $tables,
            static fn (array $table): bool => array_key_exists($table['name'], $allowed) ||
                array_key_exists($table['schema'] . '.' . $table['name'], $allowed)
        ));
    }

    /**
     * @param array<string, mixed> $config
     * @return \PgSql\Connection
     * @throws \Exception
     */
    public function connect(array $config): \PgSql\Connection
    {
        if (!function_exists('pg_connect')) {
            throw new \Exception('PHP pgsql extension is required for PostgreSQL commands.');
        }

        $parts = [
            'host' => $this->getStringConfig($config, 'host', 'localhost'),
            'port' => (string) $this->getIntConfig($config, 'port', 5432),
            'dbname' => $this->getStringConfig($config, 'database', ''),
            'user' => $this->getStringConfig($config, 'login', ''),
            'password' => $this->getStringConfig($config, 'password', ''),
        ];

        $connectionString = implode(' ', array_map(
            fn (string $key, string $value): string => $key . "='" . str_replace("'", "\\'", $value) . "'",
            array_keys($parts),
            $parts
        ));
        $connection = pg_connect($connectionString);

        if (!$connection instanceof \PgSql\Connection) {
            throw new \Exception('Unable to connect to PostgreSQL.');
        }

        return $connection;
    }

    /**
     * @param \PgSql\Connection $connection
     * @return array<int, array{schema: string, name: string}>
     * @throws \Exception
     */
    public function getTables(\PgSql\Connection $connection): array
    {
        $sql = "SELECT table_schema, table_name FROM information_schema.tables " .
            "WHERE table_type = 'BASE TABLE' AND table_schema NOT IN ('pg_catalog', 'information_schema') " .
            "ORDER BY table_schema, table_name";
        $result = pg_query($connection, $sql);

        if (!$result instanceof \PgSql\Result) {
            throw new \Exception('Unable to fetch PostgreSQL table list: ' . pg_last_error($connection));
        }

        $tables = [];

        while ($row = pg_fetch_assoc($result)) {
            $tables[] = [
                'schema' => (string) $row['table_schema'],
                'name' => (string) $row['table_name'],
            ];
        }

        pg_free_result($result);

        return $tables;
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

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
