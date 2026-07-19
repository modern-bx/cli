# Команды базы данных

Команды базы данных берут параметры подключения из Bitrix-конфигурации. Поддерживаются MySQL и PostgreSQL. Реализация работает средствами PHP и не требует `mysqldump`/`psql`/`mysql` в системе. Все команды с `--remote` поддерживают `--local`: эта опция отключает неявный remote текущей сессии и принудительно запускает команду локально.

## `db:exec [--remote=<codename>] [--local] [--page=<n>] [--size=<n>] [--php]`

Читает SQL из stdin и выполняет его в базе проекта.

Локально команда подключается к БД напрямую через настройки проекта. При `--remote` SQL отправляется на удалённый проект через admin API, а `--local` отключает remote текущей сессии. Для удалённого режима доступны пагинация результата и выполнение через PHP-консоль.

```bash
echo 'select ID, LOGIN from b_user limit 10' | php cli.phar db:exec

echo 'select * from b_user' | php cli.phar db:exec --remote=prod --page=1 --size=50
```

Опции:

- `--remote` — кодовое имя удалённого проекта.
- `--local` — отключить неявный remote текущей сессии и выполнить команду локально.
- `--page` — номер страницы результата.
- `--size` — размер страницы, по умолчанию `100`.
- `--php` — выполнить удалённый SQL через PHP-консоль.

## `db:dump [--remote=<codename>] [--local] [file] [--table=<tables>]`

Создаёт SQL-дамп базы в файл или, если файл не указан, в stdout. Можно ограничить набор таблиц через `--table`; список задаётся через запятую, для PostgreSQL допустим формат `schema.table`. С `--remote` дамп формируется на зарегистрированном удалённом проекте через PHP-консоль админки и затем сохраняется в локальный файл или выводится в stdout.

```bash
php cli.phar db:dump var/backup.sql
php cli.phar db:dump var/users.sql --table=b_user,b_user_group
php cli.phar db:dump --remote=prod var/prod.sql --table=b_user
```

## `db:apply [--remote=<codename>] [--local] [file]`

Выполняет SQL-файл или, если файл не указан, SQL из stdin в базе проекта. Если stdin пустой, команда завершается с предупреждением. Команда подходит для восстановления дампа или применения подготовленного SQL-скрипта. С `--remote` локальный SQL-файл отправляется и выполняется на зарегистрированном удалённом проекте через PHP-консоль админки.

```bash
php cli.phar db:apply var/backup.sql
php cli.phar db:apply --remote=prod var/backup.sql
cat var/backup.sql | php cli.phar db:apply --remote=prod
```

## `db:wipe [--remote=<codename>] [--local] [--table=<tables>]`

Очищает таблицы через `TRUNCATE`. Без `--table` удаляет данные из всех таблиц найденной базы, поэтому используйте команду осторожно. С `--remote` очистка выполняется на зарегистрированном удалённом проекте через PHP-консоль админки.

```bash
php cli.phar db:wipe --table=b_cache_tag,b_event
php cli.phar db:wipe --remote=prod --table=b_cache_tag
```
