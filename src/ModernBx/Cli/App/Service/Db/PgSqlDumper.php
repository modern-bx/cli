<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Db;

final class PgSqlDumper
{
    private PgSqlExecutor $executor;

    public function __construct(PgSqlExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * @param array<string, mixed> $config
     * @param string $outputFile
     * @param array<int, string>|null $tables
     * @return void
     * @throws \Exception
     */
    public function dump(array $config, string $outputFile, ?array $tables = null): void
    {
        $connection = $this->executor->connect($config);

        try {
            $this->writeDump($connection, $outputFile, $tables);
        } finally {
            pg_close($connection);
        }
    }

    /**
     * @param \PgSql\Connection $connection
     * @param string $outputFile
     * @param array<int, string>|null $tables
     * @return void
     * @throws \Exception
     */
    private function writeDump(\PgSql\Connection $connection, string $outputFile, ?array $tables): void
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
            $this->write($handle, "-- ModernBx CLI PostgreSQL dump\n");
            $this->write($handle, "-- Compatible with PostgreSQL\n\n");
            $this->write($handle, "SET client_encoding = 'UTF8';\n");
            $this->write($handle, "SET standard_conforming_strings = on;\n\n");

            foreach ($this->executor->filterTables($this->executor->getTables($connection), $tables) as $table) {
                $this->dumpTable($connection, $handle, $table['schema'], $table['name']);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param \PgSql\Connection $connection
     * @param resource $handle
     * @param string $schema
     * @param string $table
     * @return void
     * @throws \Exception
     */
    private function dumpTable(\PgSql\Connection $connection, $handle, string $schema, string $table): void
    {
        $quotedSchema = $this->executor->quoteIdentifier($schema);
        $quotedTable = $this->executor->quoteIdentifier($table);
        $fullTable = $quotedSchema . '.' . $quotedTable;

        $this->write($handle, "\n--\n-- Table structure for table {$fullTable}\n--\n\n");
        $this->write($handle, "CREATE SCHEMA IF NOT EXISTS {$quotedSchema};\n");
        $this->write($handle, "DROP TABLE IF EXISTS {$fullTable} CASCADE;\n");
        $this->write($handle, $this->getCreateTableSql($connection, $schema, $table) . "\n\n");
        $this->write($handle, "--\n-- Dumping data for table {$fullTable}\n--\n\n");
        $this->dumpRows($connection, $handle, $schema, $table);
    }

    /**
     * @param \PgSql\Connection $connection
     * @param string $schema
     * @param string $table
     * @return string
     * @throws \Exception
     */
    private function getCreateTableSql(\PgSql\Connection $connection, string $schema, string $table): string
    {
        $columns = $this->getColumns($connection, $schema, $table);
        $primaryKey = $this->getPrimaryKey($connection, $schema, $table);
        $lines = [];

        foreach ($columns as $column) {
            $line = '    ' . $this->executor->quoteIdentifier($column['name']) . ' ' . $column['type'];

            if ($column['default'] !== null) {
                $line .= ' DEFAULT ' . $column['default'];
            }

            if ($column['nullable'] === 'NO') {
                $line .= ' NOT NULL';
            }

            $lines[] = $line;
        }

        if ($primaryKey !== []) {
            $lines[] = '    PRIMARY KEY (' . implode(', ', array_map(
                fn (string $column): string => $this->executor->quoteIdentifier($column),
                $primaryKey
            )) . ')';
        }

        return 'CREATE TABLE ' . $this->executor->quoteIdentifier($schema) . '.' .
            $this->executor->quoteIdentifier($table) . " (\n" . implode(",\n", $lines) . "\n);";
    }

    /**
     * @param \PgSql\Connection $connection
     * @param string $schema
     * @param string $table
     * @return array<int, array{name: string, type: string, default: string|null, nullable: string}>
     * @throws \Exception
     */
    private function getColumns(\PgSql\Connection $connection, string $schema, string $table): array
    {
        $sql = "SELECT column_name, data_type, udt_name, character_maximum_length, numeric_precision, " .
            "numeric_scale, column_default, is_nullable FROM information_schema.columns " .
            "WHERE table_schema = $1 AND table_name = $2 ORDER BY ordinal_position";
        $result = pg_query_params($connection, $sql, [$schema, $table]);

        if (!$result instanceof \PgSql\Result) {
            throw new \Exception('Unable to fetch PostgreSQL columns: ' . pg_last_error($connection));
        }

        $columns = [];

        while ($row = pg_fetch_assoc($result)) {
            $columns[] = [
                'name' => (string) $row['column_name'],
                'type' => $this->formatType($this->normalizeRow($row)),
                'default' => $row['column_default'] === null ? null : (string) $row['column_default'],
                'nullable' => (string) $row['is_nullable'],
            ];
        }

        pg_free_result($result);

        return $columns;
    }

    /**
     * @param array<int|string, string|null> $row
     * @return array<string, string|null>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, string|null> $row
     * @return string
     */
    private function formatType(array $row): string
    {
        $type = (string) ($row['data_type'] === 'USER-DEFINED' ? $row['udt_name'] : $row['data_type']);

        if (in_array($type, ['character varying', 'character'], true) && $row['character_maximum_length'] !== null) {
            return $type . '(' . $row['character_maximum_length'] . ')';
        }

        if ($type === 'numeric' && $row['numeric_precision'] !== null) {
            $scale = $row['numeric_scale'] !== null ? ', ' . $row['numeric_scale'] : '';
            return $type . '(' . $row['numeric_precision'] . $scale . ')';
        }

        return $type;
    }

    /**
     * @param \PgSql\Connection $connection
     * @param string $schema
     * @param string $table
     * @return array<int, string>
     * @throws \Exception
     */
    private function getPrimaryKey(\PgSql\Connection $connection, string $schema, string $table): array
    {
        $sql = "SELECT kcu.column_name FROM information_schema.table_constraints tc " .
            "JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name " .
            "AND tc.table_schema = kcu.table_schema AND tc.table_name = kcu.table_name " .
            "WHERE tc.constraint_type = 'PRIMARY KEY' AND tc.table_schema = $1 AND tc.table_name = $2 " .
            "ORDER BY kcu.ordinal_position";
        $result = pg_query_params($connection, $sql, [$schema, $table]);

        if (!$result instanceof \PgSql\Result) {
            throw new \Exception('Unable to fetch PostgreSQL primary key: ' . pg_last_error($connection));
        }

        $columns = [];

        while ($row = pg_fetch_assoc($result)) {
            $columns[] = (string) $row['column_name'];
        }

        pg_free_result($result);

        return $columns;
    }

    /**
     * @param \PgSql\Connection $connection
     * @param resource $handle
     * @param string $schema
     * @param string $table
     * @return void
     * @throws \Exception
     */
    private function dumpRows(\PgSql\Connection $connection, $handle, string $schema, string $table): void
    {
        $fullTable = $this->executor->quoteIdentifier($schema) . '.' . $this->executor->quoteIdentifier($table);
        $result = pg_query($connection, 'SELECT * FROM ' . $fullTable);

        if (!$result instanceof \PgSql\Result) {
            throw new \Exception('Unable to fetch PostgreSQL rows: ' . pg_last_error($connection));
        }

        while ($row = pg_fetch_assoc($result)) {
            $columns = array_map(
                fn ($column): string => $this->executor->quoteIdentifier((string) $column),
                array_keys($row)
            );
            $values = array_map(
                fn ($value): string => $value === null ? 'NULL' : "'" . pg_escape_string($connection, $value) . "'",
                array_values($row)
            );
            $this->write(
                $handle,
                'INSERT INTO ' . $fullTable . ' (' . implode(', ', $columns) . ') VALUES (' .
                    implode(', ', $values) . ");\n"
            );
        }

        pg_free_result($result);
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
