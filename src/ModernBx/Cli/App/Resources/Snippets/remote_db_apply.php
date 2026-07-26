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
    $results = RemoteDbSnippet::executeSqlBatch($connection, $sqlDump);

    // @phpstan-ignore-next-line Служебный класс подключается к удаленному сниппету сборщиком.
    echo CommandResult::success($results);
} catch (\Throwable $err) {
    // @phpstan-ignore-next-line Служебный класс подключается к удаленному сниппету сборщиком.
    echo CommandResult::error($err->getMessage());
}
