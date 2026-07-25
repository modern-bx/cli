<?php

$__bxCliAdminerLogin = '__BX_CLI_ADMINER_LOGIN__';
$__bxCliAdminerPasswordHash = '__BX_CLI_ADMINER_PASSWORD_HASH__';
$__bxCliAdminerUser = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
$__bxCliAdminerPassword = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');

if ($__bxCliAdminerUser !== $__bxCliAdminerLogin
    || !password_verify($__bxCliAdminerPassword, $__bxCliAdminerPasswordHash)
) {
    header('WWW-Authenticate: Basic realm="Adminer"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
}

unset(
    $__bxCliAdminerLogin,
    $__bxCliAdminerPasswordHash,
    $__bxCliAdminerUser,
    $__bxCliAdminerPassword
);
