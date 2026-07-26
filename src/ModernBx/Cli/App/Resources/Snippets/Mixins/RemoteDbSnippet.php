<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

final class RemoteDbSnippet
{
    /**
     * @param array<int, string>|null $filter
     * @return array<int, string>
     */
    public static function getTables(object $connection, ?array $filter = null): array
    {
        $tables = [];
        // @phpstan-ignore-next-line Bitrix database connection API.
        $result = $connection->query(
            self::isMysql($connection)
                ? "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"
                : "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
        );

        while ($row = $result->fetch()) {
            $tables[] = (string) reset($row);
        }

        if ($filter !== null) {
            $allowed = array_flip($filter);
            $tables = array_values(array_filter(
                $tables,
                static fn (string $table): bool => array_key_exists($table, $allowed)
            ));
        }

        return $tables;
    }

    public static function isMysql(object $connection): bool
    {
        return stripos(get_class($connection), 'mysql') !== false;
    }

    public static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public static function quoteValue(object $connection, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (!is_scalar($value)) {
            $value = '';
        }

        // @phpstan-ignore-next-line Bitrix database connection API.
        return "'" . $connection->getSqlHelper()->forSql((string) $value) . "'";
    }

    /** @return array<int, array{columns: array<int, string>, rows: array<int, array<int, string|null>>}> */
    public static function executeSqlBatch(object $connection, string $sql): array
    {
        $results = [];

        foreach (self::splitSqlStatements($sql) as $statement) {
            if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN|WITH)\b/i', $statement) !== 1) {
                // @phpstan-ignore-next-line Bitrix database connection API.
                $connection->queryExecute($statement);
                continue;
            }

            // @phpstan-ignore-next-line Bitrix database connection API.
            $queryResult = $connection->query($statement);
            $rows = [];
            $columns = [];

            while ($row = $queryResult->fetch()) {
                if ($columns === []) {
                    $columns = array_map('strval', array_keys($row));
                }
                $rows[] = array_map(
                    static fn (mixed $value): ?string => $value === null
                        ? null
                        : (is_scalar($value) ? (string) $value : ''),
                    array_values($row)
                );
            }

            if ($columns !== []) {
                $results[] = ['columns' => $columns, 'rows' => $rows];
            }
        }

        return $results;
    }

    /** @return array<int, string> */
    private static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $current .= $char;

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    $current .= $i < $length ? $sql[$i] : '';
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
                continue;
            }

            if ($char !== ';') {
                continue;
            }

            $statement = trim(substr($current, 0, -1));
            $current = '';

            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        $statement = trim($current);

        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
