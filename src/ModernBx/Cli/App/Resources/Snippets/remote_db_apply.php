<?php

/**
 * @var string $sqlDump SQL dump content to apply on the remote Bitrix project.
 */
$sqlDump = '__BX_CLI_SQL_DUMP__';

try {
    if (trim($sqlDump) === '') {
        throw new \RuntimeException('SQL-файл пуст.');
    }

    // @phpstan-ignore-next-line Bitrix API доступен на удаленном проекте, где выполняется сниппет.
    $connection = \Bitrix\Main\Application::getConnection();
    RemoteDbSnippet::executeSqlBatch($connection, $sqlDump);

    echo CommandResult::success(true);
} catch (\Throwable $err) {
    echo CommandResult::error($err->getMessage());
}
