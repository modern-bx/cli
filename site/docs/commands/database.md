# Команды базы данных

Команды базы данных берут параметры подключения из Bitrix-конфигурации. Поддерживаются MySQL и PostgreSQL. Реализация работает средствами PHP и не требует `mysqldump`/`psql`/`mysql` в системе.

## `db:exec [--remote=<codename>] [--page=<n>] [--size=<n>] [--php]`

Читает SQL из stdin и выполняет его в базе проекта.

Локально команда подключается к БД напрямую через настройки проекта. При `--remote` SQL отправляется на удалённый проект через admin API. Для удалённого режима доступны пагинация результата и выполнение через PHP-консоль.

```bash
echo 'select ID, LOGIN from b_user limit 10' | php cli.phar db:exec

echo 'select * from b_user' | php cli.phar db:exec --remote=prod --page=1 --size=50
```

Опции:

- `--remote` — кодовое имя удалённого проекта.
- `--page` — номер страницы результата.
- `--size` — размер страницы, по умолчанию `100`.
- `--php` — выполнить удалённый SQL через PHP-консоль.

## `db:dump <file> [--table=<tables>]`

Создаёт SQL-дамп базы в файл. Можно ограничить набор таблиц через `--table`; список задаётся через запятую, для PostgreSQL допустим формат `schema.table`.

```bash
php cli.phar db:dump var/backup.sql
php cli.phar db:dump var/users.sql --table=b_user,b_user_group
```

## `db:apply <file>`

Выполняет SQL-файл в базе проекта. Команда подходит для восстановления дампа или применения подготовленного SQL-скрипта.

```bash
php cli.phar db:apply var/backup.sql
```

## `db:wipe [--table=<tables>]`

Очищает таблицы через `TRUNCATE`. Без `--table` удаляет данные из всех таблиц найденной базы, поэтому используйте команду осторожно.

```bash
php cli.phar db:wipe --table=b_cache_tag,b_event
```
