<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

final class RemoteUserPhpCodeBuilder
{
    use RemoteSnippetMixins;

    private const SNIPPET = __DIR__ . '/../../Resources/Snippets/remote_user_password_set.php';

    public function buildPasswordSet(string $field, string $value, string $password): string
    {
        $snippet = file_get_contents(self::SNIPPET);

        if ($snippet === false) {
            throw new \RuntimeException('Не удалось загрузить PHP-сниппет для смены пароля пользователя.');
        }

        return $this->withSnippetMixins(strtr($snippet, [
            "\$field = '__BX_CLI_USER_FIELD__';" => '$field = ' . var_export($field, true) . ';',
            "\$value = '__BX_CLI_USER_VALUE__';" => '$value = ' . var_export($value, true) . ';',
            "\$password = '__BX_CLI_USER_PASSWORD__';" => '$password = ' . var_export($password, true) . ';',
        ]));
    }
}
