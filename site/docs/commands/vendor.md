# Vendor и Adminer

Команды этой группы скачивают дистрибутивы и управляют вспомогательным ПО. Сейчас `vendor:install` и `vendor:uninstall` поддерживают пакет `adminer`.

## `vendor:get-installer [--product=bitrix|bitrix24] [--edition=<edition>] [--path=<path>] [--extract]`

Скачивает официальный установочный архив 1С-Битрикс или 1С-Битрикс24. По умолчанию выбираются продукт `bitrix`, редакция `start` и текущая директория. Повторный запуск использует уже скачанный непустой файл. `--extract` распаковывает архив рядом с ним и удаляет архив после успешного извлечения.

```bash
php cli.phar vendor:get-installer --product=bitrix --edition=standard --path=./dist
php cli.phar vendor:get-installer --product=bitrix24 --edition=business --extract
```

Актуальный список редакций выводится через `php cli.phar help vendor:get-installer` и при ошибке в значении `--edition`.

## `vendor:install [--remote=<codename>] [--local] [--path=<directory>] adminer`

Устанавливает Adminer и добавляет HTTP Basic Auth. Команда генерирует учётные данные, а после установки печатает полный URL, логин и пароль. Без `--path` используется стандартный каталог пакета; `--remote` устанавливает его на зарегистрированный проект, а `--local` отключает remote текущей сессии.

```bash
php cli.phar vendor:install adminer
php cli.phar vendor:install --remote=prod --path=/tools/adminer adminer
```

## `vendor:uninstall [--remote=<codename>] [--local] [--path=<directory>] adminer`

Удаляет установленный Adminer. Путь разрешается по тем же правилам, что и при установке.

```bash
php cli.phar vendor:uninstall --remote=prod adminer
```

## `adminer:import --remote=<codename> --password=<http-password> [options] <src>`

Загружает локальный `.sql` или `.gz` на remote и импортирует его через установленный Adminer. Параметры БД по умолчанию берутся из `project.yaml`; при необходимости их можно переопределить опциями `--db.engine`, `--db.host`, `--db.username`, `--db.password` и `--db.database`.

Основные опции:

- `--path` — каталог Adminer относительно document root, по умолчанию `/`;
- `--force` — перезаписать ранее загруженный дамп;
- `--no-delete` — оставить дамп на remote после импорта;
- `--format=table|csv|json` — формат результата;
- `--void` — не выводить результат импорта;
- `-v`/`--verbose` — показать этапы загрузки, авторизации и импорта.

```bash
php cli.phar adminer:import --remote=prod --password='http-secret' backup.sql.gz
php cli.phar adminer:import -v --remote=prod --password='http-secret' --format=json backup.sql
```
