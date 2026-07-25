# Удалённые проекты

Удалённый проект — это Bitrix-инсталляция, которой CLI может управлять через административный endpoint. После регистрации команды с опцией `--remote` выполняют операции не в текущем document root, а на выбранном проекте.

## Где хранится registry

Конфигурации лежат в домашнем каталоге пользователя:

```text
~/.config/bx-cli/projects/<codename>/project.yaml
```

## Регистрация

```bash
php cli.phar remote:register https://example.org prod
```

Команда нормализует endpoint, авторизуется в `/bitrix/admin/`, сохраняет имя проекта, endpoint, учётные данные, PHPSESSID и параметры базы данных. Параметры `db.engine`, `db.host`, `db.username`, `db.password` и `db.database` автоматически читаются из ядра Bitrix и записываются в `project.yaml`. Если кодовое имя не передано, по умолчанию используется host endpoint.

## Выбор remote для сессии

```bash
eval "$(php cli.phar session:remote prod)"
```

После этого команды, поддерживающие session remote, могут использовать `prod` без явной передачи `--remote`. Сброс:

```bash
eval "$(php cli.phar session:remote --unset)"
```
