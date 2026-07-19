# Команды файловой системы

Файловые команды работают с путями относительно document root проекта. Часть команд поддерживает локальный и удалённый режим, а загрузка файлов (`file:put`) предназначена для удалённых проектов.

## `file:list [--remote=<codename>] [--short] <expr>`

Показывает файлы по пути директории или glob-выражению.

```bash
php cli.phar file:list local/templates
php cli.phar file:list --short 'upload/*.jpg'
php cli.phar file:list --remote=prod bitrix/admin
```

## `file:get [--remote=<codename>] <src> <dest>`

Копирует файл из проекта в локальный путь назначения. В локальном режиме источник берётся из текущего Bitrix-проекта; в удалённом — скачивается из зарегистрированного проекта.

```bash
php cli.phar file:get local/php_interface/init.php ./init.php
php cli.phar file:get --remote=prod upload/report.csv ./report.csv
```

Если `dest` — директория, исходное имя файла сохраняется.

## `file:put --remote=<codename> [--force] <src> <dest>`

Загружает локальный файл в удалённый проект. Назначение задаётся относительно document root удалённого проекта. Если `dest` оканчивается на `/`, используется исходное имя файла.

```bash
php cli.phar file:put --remote=prod ./logo.svg local/templates/site/assets/logo.svg
php cli.phar file:put --remote=prod --force ./robots.txt robots.txt
```

Опция `--force` удаляет удалённый файл перед загрузкой.

## `file:mkdir [--remote=<codename>] <directory-path>`

Создает директорию по пути относительно document root локального или удалённого проекта. Промежуточные директории в локальном режиме создаются автоматически.

```bash
php cli.phar file:mkdir local/cache/custom
php cli.phar file:mkdir --remote=prod local/cache/custom
```

## `file:delete [--remote=<codename>] <path>`

Удаляет файл по пути относительно document root локального или удалённого проекта.

```bash
php cli.phar file:delete upload/old.csv
php cli.phar file:delete --remote=prod upload/old.csv
```

## `file:rmdir [--remote=<codename>] <path>`

Удаляет директорию по пути относительно document root локального или удалённого проекта. Команда работает как отдельный алиас логики `file:delete`.

```bash
php cli.phar file:rmdir upload/old-dir
php cli.phar file:rmdir --remote=prod upload/old-dir
```
