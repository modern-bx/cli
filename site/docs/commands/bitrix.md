# Команды Bitrix

Большинство команд этого раздела рассчитаны на запуск изнутри Bitrix-проекта: CLI ищет каталог `bitrix` вверх от текущей директории и подключает ядро перед выполнением бизнес-логики. Исключение — `backup:extract`: она работает с переданным архивом напрямую и не требует document root.

## `backup:extract [--password=<password>] <archive> <destination>`

Распаковывает архив резервной копии Bitrix в указанный каталог. Команда не подключает ядро проекта и описана отдельно в разделе [Резервные копии](./backup.md).

```bash
php cli.phar backup:extract ./backup.tar.gz ./restore
php cli.phar backup:extract --password='secret' ./backup.enc.gz ./restore
```

## `cache:clear [directory...]`

Очищает кеш Bitrix. Без аргументов удаляет содержимое стандартных каталогов:

- `cache`
- `managed_cache`
- `stack_cache`

Можно передать один или несколько каталогов из допустимого списка.

```bash
php cli.phar cache:clear
php cli.phar cache:clear managed_cache
```

## `module:install <module>`

Устанавливает модуль Bitrix:

1. Проверяет, не установлен ли модуль уже.
2. Ищет модуль в `bitrix/modules/<module>` и `local/modules/<module>`.
3. Подключает `install/index.php`.
4. Создаёт installer-класс, где точки в коде модуля заменены на подчёркивания.
5. Вызывает `DoInstall()`.

```bash
php cli.phar module:install vendor.module
```

## `module:uninstall <module>`

Удаляет установленный модуль. Логика аналогична установке, но вместо `DoInstall()` вызывается `DoUninstall()`.

```bash
php cli.phar module:uninstall vendor.module
```

## `module:reinstall <module>`

Последовательно выполняет внутреннюю логику `module:uninstall` и `module:install`. Команда удобна при разработке миграций и install-скриптов.

```bash
php cli.phar module:reinstall vendor.module
```

## `module:version <module...>`

Печатает версии указанных модулей. Можно передать несколько кодов.

```bash
php cli.phar module:version main sale vendor.module
```

## `option:get [--unserialize] <option...>`

Читает опции модулей. Формат аргумента:

```text
module.option[.lid]
```

Если передан `--unserialize`, CLI пытается десериализовать значение перед выводом.

```bash
php cli.phar option:get main.site_name
php cli.phar option:get --unserialize vendor.module.complex_option
```

## `option:set <option> <value>`

Записывает значение опции Bitrix. Формат имени такой же, как у `option:get`.

```bash
php cli.phar option:set main.site_name 'Новый сайт'
php cli.phar option:set main.some_option.s1 value
```

## `setting:get [--extra] [--pretty] <path>`

Читает значение из `.settings.php` или, с `--extra`, из `.settings_extra.php`. Путь задаётся без корневого сегмента `value`.

```bash
php cli.phar setting:get connections.default.host
php cli.phar setting:get --pretty connections.default
php cli.phar setting:get --extra cache.type
```

## `setting:set [--extra] <path> <value>`

Изменяет `.settings.php` или `.settings_extra.php`. Значение декодируется как JSON, если это возможно.

```bash
php cli.phar setting:set cache.type '{"value":"memcache"}'
php cli.phar setting:set --extra custom.flag true
```

## `site:list [--remote=<codename>] [--filter=<json>] [--order=<json>] [--select=<json>] [--pretty] [--short[=<format>]] [--format=<table|csv>]`

Печатает сайты через `Bitrix\Main\SiteTable::getList`. По умолчанию выводит JSON-строки, с `--pretty` форматирует JSON. Опции `--short` и `--format` переключают вывод из JSON в строковый шаблон, консольную таблицу или CSV; они несовместимы между собой. Опции `filter`, `order`, `select` напрямую соответствуют параметрам D7. С `--remote` выполняет D7-операцию на зарегистрированном удалённом проекте через PHP-консоль админки.

```bash
php cli.phar site:list
php cli.phar site:list --filter='{"ACTIVE":"Y"}' --order='{"SORT":"ASC"}'
php cli.phar site:list --remote=prod --select='["LID","NAME"]'
php cli.phar site:list --pretty
php cli.phar site:list --short
php cli.phar site:list --short='[LID] NAME [SERVER_NAME]'
php cli.phar site:list --format=table
php cli.phar site:list --format=csv
```

## `site:get [--remote=<codename>] [--select=<json>] <id>`

Печатает поля одного сайта по LID. С `--remote` получает сайт на зарегистрированном удалённом проекте через D7 `SiteTable`.

```bash
php cli.phar site:get s1
php cli.phar site:get --select='["LID","NAME","DIR"]' s1
php cli.phar site:get --remote=prod s1
```

## `site:update [--remote=<codename>] <LID> <fields-json>`

Обновляет поля сайта через `SiteTable::update`. Второй аргумент должен быть JSON-объектом. С `--remote` валидирует JSON локально, а обновление выполняет на зарегистрированном удалённом проекте через D7.

```bash
php cli.phar site:update s1 '{"NAME":"Основной сайт"}'
php cli.phar site:update --remote=prod s1 '{"NAME":"Основной сайт"}'
```

## `php:exec [--remote=<codename>]`

Читает PHP-код из stdin и выполняет его после подключения ядра Bitrix. Без `--remote` код исполняется локально; с `--remote` отправляется в зарегистрированный удалённый проект.

```bash
echo 'echo \Bitrix\Main\Context::getCurrent()->getServer()->getDocumentRoot();' | php cli.phar php:exec

echo 'echo "ok";' | php cli.phar php:exec --remote=prod
```
