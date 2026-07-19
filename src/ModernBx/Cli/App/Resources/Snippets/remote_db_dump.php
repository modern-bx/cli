<?php

/**
 * @var array<int, string>|null $tableFilter Tables to dump. Null means all base tables.
 */
$tableFilter = null;

try {
    // @phpstan-ignore-next-line Bitrix API доступен на удаленном проекте, где выполняется сниппет.
    $connection = \Bitrix\Main\Application::getConnection();
    $tables = RemoteDbSnippet::getTables($connection, $tableFilter);
    $sql = "-- ModernBx CLI remote database dump\n\n";

    if (RemoteDbSnippet::isMysql($connection)) {
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    }

    foreach ($tables as $table) {
        $quoted = RemoteDbSnippet::quoteIdentifier($table);
        $sql .= "\n--\n-- Table data for table {$quoted}\n--\n\n";
        $result = $connection->query('SELECT * FROM ' . $quoted);

        while ($row = $result->fetch()) {
            $columns = array_map([RemoteDbSnippet::class, 'quoteIdentifier'], array_keys($row));
            $values = array_map(static fn ($value): string => RemoteDbSnippet::quoteValue($connection, $value), array_values($row));
            $sql .= 'INSERT INTO ' . $quoted . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
        }
    }

    if (RemoteDbSnippet::isMysql($connection)) {
        $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
    }

    echo CommandResult::success($sql);
} catch (\Throwable $err) {
    echo CommandResult::error($err->getMessage());
}
