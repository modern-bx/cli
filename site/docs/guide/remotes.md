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

Обновить параметры уже зарегистрированного проекта можно без повторного ввода доступов:

```bash
php cli.phar remote:register --update prod
```

Команда использует endpoint, учётные данные и сессию из сохранённого `project.yaml`, заново запрашивает параметры у remote и заменяет секцию `options`.

## Выбор remote для сессии

```bash
eval "$(php cli.phar session:remote prod)"
```

После этого команды, поддерживающие session remote, могут использовать `prod` без явной передачи `--remote`. Сброс:

```bash
eval "$(php cli.phar session:remote --unset)"
```
