<?php

/**
 * @var array<int, string>|null $tableFilter Tables to truncate. Null means all base tables.
 */
$tableFilter = null;

try {
    // @phpstan-ignore-next-line Bitrix API доступен на удаленном проекте, где выполняется сниппет.
    $connection = \Bitrix\Main\Application::getConnection();
    $tables = RemoteDbSnippet::getTables($connection, $tableFilter);

    if (RemoteDbSnippet::isMysql($connection)) {
        $connection->queryExecute('SET FOREIGN_KEY_CHECKS=0');
    }

    try {
        foreach ($tables as $table) {
            $connection->queryExecute('TRUNCATE TABLE ' . RemoteDbSnippet::quoteIdentifier($table));
        }
    } finally {
        if (RemoteDbSnippet::isMysql($connection)) {
            $connection->queryExecute('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    echo CommandResult::success(count($tables));
} catch (\Throwable $err) {
    echo CommandResult::error($err->getMessage());
}
