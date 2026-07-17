<?php

/**
 * @var string $sqlQuery SQL-запрос, который нужно выполнить на удаленном Битрикс-проекте.
 *                  Значение подставляется сборщиком кода как строковый литерал PHP.
 */
$sqlQuery = '__BX_CLI_SQL_QUERY__';

/**
 * @var int $pageNumber Номер страницы результата, начиная с 1.
 *                 Используется для расчета смещения при выборке через Bitrix DB connection.
 */
$pageNumber = 1;

/**
 * @var int $pageSize Размер страницы результата.
 *               Передается в query() как лимит выборки и должен быть положительным целым числом.
 */
$pageSize = 100;

try {
    $offset = ($pageNumber - 1) * $pageSize;
    // @phpstan-ignore-next-line Bitrix API доступен на удаленном проекте, где выполняется сниппет.
    $connection = \Bitrix\Main\Application::getConnection();
    $result = $connection->query($sqlQuery, $pageSize, $offset);
    $columns = [];
    $rows = [];

    while ($row = $result->fetch()) {
        if ($columns === []) {
            $columns = array_keys($row);
        }

        $rows[] = array_values($row);
    }

    echo json_encode([
        'ok' => true,
        'columns' => $columns,
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $err) {
    echo json_encode([
        'ok' => false,
        'error' => $err->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
