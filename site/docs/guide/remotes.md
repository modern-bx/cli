# Удалённые проекты

Удалённый проект — это Bitrix-инсталляция, которой CLI может управлять через административный endpoint. После регистрации команды с опцией `--remote` выполняют операции не в текущем document root, а на выбранном проекте.

## Где хранится registry

Конфигурации лежат в домашнем каталоге пользователя:

```text
~/.config/bx-cli/projects/<codename>/project.yaml
```

## Регистрация

```bash
php cli.phar remote:register https://example.org admin password --name prod
```

Команда нормализует endpoint, авторизуется в `/bitrix/admin/`, сохраняет имя проекта, endpoint, учётные данные и PHPSESSID.

## Выбор remote для сессии

```bash
eval "$(php cli.phar session:remote prod)"
```

После этого команды, поддерживающие session remote, могут использовать `prod` без явной передачи `--remote`. Сброс:

```bash
eval "$(php cli.phar session:remote --unset)"
```
