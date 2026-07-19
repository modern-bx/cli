<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

final class RemoteDbSnippet
{
    /** @return array<int, string> */
    public static function getTables(object $connection, ?array $filter = null): array
    {
        $tables = [];
        $result = $connection->query(self::isMysql($connection)
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

        return "'" . $connection->getSqlHelper()->forSql((string) $value) . "'";
    }

    public static function executeSqlBatch(object $connection, string $sql): void
    {
        foreach (self::splitSqlStatements($sql) as $statement) {
            $connection->queryExecute($statement);
        }
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
