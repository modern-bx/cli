# Команды файловой системы

Файловые команды работают с путями относительно document root проекта. Часть команд поддерживает локальный и удалённый режим, а загрузка файлов (`file:put`) предназначена для удалённых проектов. Команды с `--remote` поддерживают `--local`: эта опция отключает неявный remote текущей сессии и принудительно запускает локальный режим там, где команда умеет работать локально.

## `file:list [--remote=<codename>] [--local] [--short] <expr>`

Показывает файлы по пути директории или glob-выражению.

```bash
php cli.phar file:list local/templates
php cli.phar file:list --short 'upload/*.jpg'
php cli.phar file:list --remote=prod bitrix/admin
```

## `file:get [--remote=<codename>] [--local] [--compress=zip] <src> <dest>`

Копирует файл из проекта в локальный путь назначения. В локальном режиме источник берётся из текущего Bitrix-проекта; в удалённом — скачивается из зарегистрированного проекта.

```bash
php cli.phar file:get local/php_interface/init.php ./init.php
php cli.phar file:get --remote=prod upload/report.csv ./report.csv
php cli.phar file:get --remote=prod --compress=zip upload/reports ./reports.zip
```

Если `dest` — директория, исходное имя файла сохраняется. С опцией `--compress=zip` удалённый источник может быть файлом или папкой: перед скачиванием команда создаёт zip-архив средствами PHP-расширения `ZipArchive` в `/bitrix/tmp/bx-cli/compress/<дата>/<уникальный-id>/`, скачивает архив как обычный файл и затем удаляет временный архив на удалённом сервере. Временные директории остаются на сервере для повторного использования.


## `file:extract [--remote=<codename>] [--local] [--format=zip] [--force] <src> <dest>`

Распаковывает архив из файловой структуры проекта в папку назначения относительно document root. Если `--format` не указан, команда пытается определить формат по расширению архива; сейчас поддерживается только `zip` через PHP-расширение `ZipArchive`. Папка назначения и промежуточные директории создаются автоматически, а уже существующая папка не считается ошибкой.

Если при распаковке в папке назначения уже есть файл с тем же именем, без `--force` команда завершится ошибкой. С `--force` файл будет перезаписан, а предупреждение выводится только в verbose-режиме.

```bash
php cli.phar file:extract upload/reports.zip upload/reports
php cli.phar file:extract --remote=prod --format=zip --force upload/reports.zip upload/reports
php cli.phar -vvv file:extract --remote=prod --force upload/reports.zip upload/reports
```

## `file:put --remote=<codename> [--local] [--force] [--chunk-size=<byte-count>] <src> <dest>`

Загружает локальный файл в удалённый проект. Назначение задаётся относительно document root удалённого проекта. Если `dest` оканчивается на `/`, используется исходное имя файла.

```bash
php cli.phar file:put --remote=prod ./logo.svg local/templates/site/assets/logo.svg
php cli.phar file:put --remote=prod --force ./robots.txt robots.txt
php cli.phar file:put --remote=prod --chunk-size=5242880 ./video.mp4 upload/video.mp4
```

Опция `--force` удаляет удалённый файл перед загрузкой. Если файл больше PHP-лимитов удалённого проекта, команда автоматически делит его на части, загружает временные файлы в ту же директорию, выводит прогресс по частям в человекочитаемом виде и затем объединяет части на сервере через потоки без загрузки всего файла в память. Опция `--chunk-size` задаёт размер части в байтах вручную и переопределяет размер, рассчитанный по настройкам сервера. `file:put` остаётся командой удалённой загрузки: `--local` только отключает неявный remote текущей сессии, но явный `--remote` всё равно обязателен.

## `file:apply [--remote=<codename>] [--local] [--force] [--yes] [--chunk-size=<byte-count>] <directory src> <directory dest>`

Воссоздаёт структуру локальной директории в директории назначения относительно document root локального или удалённого проекта и поштучно загружает файлы.

Перед загрузкой команда проверяет конфликты через целевой проект: существующие директории выводятся как notice, существующие файлы — как ошибки. С опцией `--force` существующие файлы становятся notice и перезаписываются. Если после диагностики есть замечания, команда запросит подтверждение; опция `--yes` отключает запрос.

В удалённом режиме большие файлы внутри директории загружаются той же chunked-логикой, что и `file:put`: часть файла читается локально, отправляется временным файлом в целевую директорию, после загрузки всех частей сервер объединяет их через потоки и удаляет временные файлы. Опция `--chunk-size` задаёт размер части в байтах вручную и переопределяет автоматический расчёт по PHP-лимитам удалённого проекта.

```bash
php cli.phar file:apply ./dist local/templates/site/assets
php cli.phar file:apply --remote=prod --force --yes ./dist local/templates/site/assets
php cli.phar file:apply --remote=prod --chunk-size=5242880 ./dist local/templates/site/assets
```


## `cfile:save [--remote=<codename>] [--local] [--short] <file>`

Создаёт запись в таблице `b_file` для уже существующего файла через `CFile::MakeFileArray()` и `CFile::SaveFile()`. Путь задаётся относительно document root локального или удалённого проекта. По умолчанию команда выводит JSON-объект со всеми полями созданной строки `b_file`, а с опцией `--short` — только ID.

```bash
php cli.phar cfile:save upload/logo.png
php cli.phar cfile:save --short upload/logo.png
php cli.phar cfile:save --remote=prod upload/logo.png
```

## `cfile:get [--remote=<codename>] [--local] <ID>`

Возвращает JSON с результатом `CFile::GetFileArray()` для указанного ID. Если запись с таким ID не найдена в `b_file`, команда завершается ошибкой.

```bash
php cli.phar cfile:get 123
php cli.phar cfile:get --remote=prod 123
```

## `cfile:delete [--remote=<codename>] [--local] [--force] <ID>`

Удаляет файл по ID через `CFile::Delete()`: запись удаляется из `b_file`, а связанный файл удаляется с диска штатной логикой Bitrix. Если ID не найден в `b_file`, без `--force` команда завершается ошибкой, а с `--force` ничего не делает.

```bash
php cli.phar cfile:delete 123
php cli.phar cfile:delete --force 123
php cli.phar cfile:delete --remote=prod 123
```

## `file:mkdir [--remote=<codename>] [--local] <directory-path>`

Создает директорию по пути относительно document root локального или удалённого проекта. Промежуточные директории в локальном режиме создаются автоматически.

```bash
php cli.phar file:mkdir local/cache/custom
php cli.phar file:mkdir --remote=prod local/cache/custom
```

## `file:delete [--remote=<codename>] [--local] <path>`

Удаляет файл по пути относительно document root локального или удалённого проекта.

```bash
php cli.phar file:delete upload/old.csv
php cli.phar file:delete --remote=prod upload/old.csv
```

## `file:rmdir [--remote=<codename>] [--local] <path>`

Удаляет директорию по пути относительно document root локального или удалённого проекта. Команда работает как отдельный алиас логики `file:delete`.

```bash
php cli.phar file:rmdir upload/old-dir
php cli.phar file:rmdir --remote=prod upload/old-dir
```
