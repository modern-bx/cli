<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Sql;

final class MySqlDumper
{
    /**
     * @param array<string, mixed> $config
     * @param string $outputFile
     * @return void
     * @throws \Exception
     */
    public function dump(array $config, string $outputFile): void
    {
        $connection = $this->connect($config);

        try {
            $this->writeDump($connection, $config, $outputFile);
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
            throw new \Exception('PHP mysqli extension is required for MySQL dumps.');
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
        $connection->query("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
        $connection->query('SET SESSION time_zone = \'+00:00\'');

        return $connection;
    }

    /**
     * @param \mysqli $connection
     * @param array<string, mixed> $config
     * @param string $outputFile
     * @return void
     * @throws \Exception
     */
    private function writeDump(\mysqli $connection, array $config, string $outputFile): void
    {
        $directory = dirname($outputFile);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \Exception('Unable to create dump directory: ' . $directory);
        }

        $handle = fopen($outputFile, 'wb');

        if (!$handle) {
            throw new \Exception('Unable to open dump file for writing: ' . $outputFile);
        }

        try {
            $this->write($handle, "-- ModernBx CLI MySQL dump\n");
            $this->write($handle, "-- Compatible with MySQL 8.0\n\n");
            $charset = $this->getStringConfig($config, 'charset', 'utf8mb4');
            $this->write($handle, 'SET NAMES ' . $this->quoteIdentifierPart($charset) . ";\n");
            $this->write($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            $this->write($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            foreach ($this->getTables($connection) as $table) {
                $this->dumpTable($connection, $handle, $table);
            }

            $this->write($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param \mysqli $connection
     * @return array<int, string>
     * @throws \Exception
     */
    private function getTables(\mysqli $connection): array
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

        return $tables;
    }

    /**
     * @param \mysqli $connection
     * @param resource $handle
     * @param string $table
     * @return void
     * @throws \Exception
     */
    private function dumpTable(\mysqli $connection, $handle, string $table): void
    {
        $quotedTable = $this->quoteIdentifier($table);
        $this->write($handle, "\n--\n-- Table structure for table {$quotedTable}\n--\n\n");
        $this->write($handle, "DROP TABLE IF EXISTS {$quotedTable};\n");

        $createResult = $connection->query('SHOW CREATE TABLE ' . $quotedTable);

        if (!$createResult instanceof \mysqli_result) {
            throw new \Exception('Unable to fetch table structure for ' . $table . ': ' . $connection->error);
        }

        $createRow = $createResult->fetch_assoc();
        $createResult->free();
        $createSql = (string) ($createRow['Create Table'] ?? '');

        if ($createSql === '') {
            throw new \Exception('Empty table structure for ' . $table . '.');
        }

        $this->write($handle, $createSql . ";\n\n");
        $this->write($handle, "--\n-- Dumping data for table {$quotedTable}\n--\n\n");

        $result = $connection->query('SELECT * FROM ' . $quotedTable, MYSQLI_USE_RESULT);

        if (!$result instanceof \mysqli_result) {
            throw new \Exception('Unable to fetch table data for ' . $table . ': ' . $connection->error);
        }

        while ($row = $result->fetch_assoc()) {
            $columns = array_map([$this, 'quoteIdentifier'], array_keys($row));
            $values = array_map(
                fn ($value): string => $this->quoteValue($connection, $value),
                array_values($row)
            );

            $this->write(
                $handle,
                'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns) . ') ' .
                    'VALUES (' . implode(', ', $values) . ");\n"
            );
        }

        $result->free();
    }

    /**
     * @param \mysqli $connection
     * @param mixed $value
     * @return string
     */
    private function quoteValue(\mysqli $connection, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (!is_scalar($value)) {
            $value = '';
        }

        return "'" . $connection->real_escape_string((string) $value) . "'";
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

    private function quoteIdentifierPart(string $identifier): string
    {
        return str_replace(['`', ';', ' ', "\n", "\r"], '', $identifier);
    }

    /**
     * @param resource $handle
     * @param string $content
     * @return void
     * @throws \Exception
     */
    private function write($handle, string $content): void
    {
        if (fwrite($handle, $content) === false) {
            throw new \Exception('Unable to write dump file.');
        }
    }
}
