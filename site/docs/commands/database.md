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

## `db:dump [--remote=<codename>] [--local] [--compress=zip] [file] [--table=<tables>]`

Создаёт SQL-дамп базы в файл или, если файл не указан, в stdout. Можно ограничить набор таблиц через `--table`; список задаётся через запятую, для PostgreSQL допустим формат `schema.table`. С `--remote` дамп формируется на зарегистрированном удалённом проекте через PHP-консоль админки и затем сохраняется в локальный файл или выводится в stdout.

Опция `--compress=zip` упаковывает дамп в ZIP. Для локального и удалённого режима требуется PHP-расширение `ZipArchive`, а аргумент `file` становится обязательным. Для имени с расширением `.sql` архив получает такое же имя с расширением `.zip`; в остальных случаях `.zip` добавляется к имени.

```bash
php cli.phar db:dump var/backup.sql
php cli.phar db:dump var/users.sql --table=b_user,b_user_group
php cli.phar db:dump --remote=prod var/prod.sql --table=b_user
php cli.phar db:dump --remote=prod --compress=zip var/prod.sql
```

## `db:apply [--remote=<codename>] [--local] [--format=table|json|csv] [--void] [file]`

Выполняет SQL-файл или, если файл не указан, SQL из stdin в базе проекта. Аргументом также может быть директория или glob-выражение с файлами `.sql` и `.zip`. Из ZIP читаются SQL-файлы верхнего уровня, после чего все найденные скрипты выполняются по порядку. Если stdin пустой, команда завершается с предупреждением. С `--remote` скрипты выполняются на зарегистрированном проекте через PHP-консоль админки.

Результаты каждого SQL-скрипта выводятся таблицами. `--format=json` и `--format=csv` переключают формат, а `--void` полностью отключает вывод результатов. Для запросов без результирующего набора выводится число затронутых строк.

```bash
php cli.phar db:apply var/backup.sql
php cli.phar db:apply --remote=prod var/backup.sql
php cli.phar db:apply --remote=prod --format=json var/backup.zip
php cli.phar db:apply --void var/migrations.sql
cat var/backup.sql | php cli.phar db:apply --remote=prod
```

## `db:wipe [--remote=<codename>] [--local] [--table=<tables>]`

Очищает таблицы через `TRUNCATE`. Без `--table` удаляет данные из всех таблиц найденной базы, поэтому используйте команду осторожно. С `--remote` очистка выполняется на зарегистрированном удалённом проекте через PHP-консоль админки.

```bash
php cli.phar db:wipe --table=b_cache_tag,b_event
php cli.phar db:wipe --remote=prod --table=b_cache_tag
```
