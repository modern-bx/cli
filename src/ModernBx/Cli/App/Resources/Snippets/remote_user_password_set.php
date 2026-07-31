<?php

$field = '__BX_CLI_USER_FIELD__';
$value = '__BX_CLI_USER_VALUE__';
$password = '__BX_CLI_USER_PASSWORD__';

try {
    if (!class_exists('CUser')) {
        throw new \RuntimeException('Класс CUser недоступен на удаленном проекте.');
    }

    $filter = [$field => $value];
    $users = \CUser::GetList($by = 'id', $order = 'asc', $filter, ['FIELDS' => ['ID']]);
    $user = $users->Fetch();

    if (!is_array($user) || !isset($user['ID'])) {
        throw new \RuntimeException('Пользователь не найден.');
    }

    $updater = new \CUser();

    if (!$updater->Update((int) $user['ID'], ['PASSWORD' => $password, 'CONFIRM_PASSWORD' => $password])) {
        throw new \RuntimeException($updater->LAST_ERROR ?: 'Не удалось изменить пароль пользователя.');
    }

    /** @phpstan-ignore-next-line */
    echo CommandResult::success(true);
} catch (\Throwable $err) {
    /** @phpstan-ignore-next-line */
    echo CommandResult::error($err->getMessage());
}
