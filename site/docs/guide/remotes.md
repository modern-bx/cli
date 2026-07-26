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
php cli.phar remote:register -v https://example.org prod
```

Команда нормализует endpoint, авторизуется в `/bitrix/admin/`, сохраняет имя проекта, endpoint, учётные данные, PHPSESSID и параметры базы данных. Параметры `db.engine`, `db.host`, `db.username`, `db.password` и `db.database` автоматически читаются из ядра Bitrix и записываются в `project.yaml`. Если кодовое имя не передано, по умолчанию используется host endpoint.

При проблемах регистрации используйте `-v`/`--verbose`. В консоли появятся HTTP-статусы, а полные запросы и ответы будут записаны в `~/.config/bx-cli/logs/remote-register-*.log`. Файл доступен только текущему пользователю (`0600`). Он содержит cookies для диагностики сессии, поэтому передавать его следует как конфиденциальные данные; пароль администратора и заголовок `Authorization` в лог не записываются.

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
