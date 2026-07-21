# Команды Bitrix

Большинство команд этого раздела рассчитаны на запуск изнутри Bitrix-проекта: CLI ищет каталог `bitrix` вверх от текущей директории и подключает ядро перед выполнением бизнес-логики. Исключение — `backup:extract`: она работает с переданным архивом напрямую и не требует document root. Команды с опцией `--remote` также поддерживают `--local`: она отключает неявный remote текущей сессии и принудительно запускает команду локально.

## `backup:extract [--password=<password>] <archive> <destination>`

Распаковывает архив резервной копии Bitrix в указанный каталог. Команда не подключает ядро проекта и описана отдельно в разделе [Резервные копии](./backup.md).

```bash
php cli.phar backup:extract ./backup.tar.gz ./restore
php cli.phar backup:extract --password='secret' ./backup.enc.gz ./restore
```

## `cache:clear [--remote=<codename>] [--local] [directory...]`

Очищает кеш Bitrix. Без аргументов удаляет содержимое стандартных каталогов:

- `cache`
- `managed_cache`
- `stack_cache`

Можно передать один или несколько каталогов из допустимого списка. С `--remote` очистка выполняется на зарегистрированном удалённом проекте через PHP-консоль админки; корневые папки кеша сохраняются, удаляется только их содержимое. После выполнения выводится статистика по каждой папке; подробный список ошибок удаления печатается только при `--verbose`/`-v`.

```bash
php cli.phar cache:clear
php cli.phar cache:clear managed_cache
php cli.phar cache:clear --remote=prod
```

## `module:install [--remote=<codename>] [--local] <module>`

Устанавливает модуль Bitrix:

1. Проверяет, не установлен ли модуль уже.
2. Ищет модуль в `bitrix/modules/<module>` и `local/modules/<module>`.
3. Подключает `install/index.php`.
4. Создаёт installer-класс, где точки в коде модуля заменены на подчёркивания.
5. Вызывает `DoInstall()`.

```bash
php cli.phar module:install vendor.module
```

## `module:uninstall [--remote=<codename>] [--local] <module>`

Удаляет установленный модуль. Логика аналогична установке, но вместо `DoInstall()` вызывается `DoUninstall()`.

```bash
php cli.phar module:uninstall vendor.module
```

## `module:reinstall [--remote=<codename>] [--local] <module>`

Последовательно выполняет внутреннюю логику `module:uninstall` и `module:install`. Команда удобна при разработке миграций и install-скриптов.

```bash
php cli.phar module:reinstall vendor.module
```

## `module:version [--remote=<codename>] [--local] <module...>`

Печатает версии указанных модулей. Можно передать несколько кодов.

```bash
php cli.phar module:version main sale vendor.module
```

## `option:get [--remote=<codename>] [--local] [--unserialize] <option>`

Читает опции модулей. Формат аргумента:

```text
module.option[.lid]
```

Если передан `--unserialize`, CLI пытается десериализовать значение перед выводом. Опция `--remote` выполняет чтение через административную PHP-консоль зарегистрированного удалённого проекта, а `--local` отключает неявный remote текущей сессии.

```bash
php cli.phar option:get main.site_name
php cli.phar option:get --unserialize vendor.module.complex_option
php cli.phar option:get --remote prod main.site_name
```

## `option:set [--remote=<codename>] [--local] <option> <value>`

Записывает значение опции Bitrix. Формат имени такой же, как у `option:get`. Опция `--remote` выполняет запись через административную PHP-консоль зарегистрированного удалённого проекта, а `--local` отключает неявный remote текущей сессии.

```bash
php cli.phar option:set main.site_name 'Новый сайт'
php cli.phar option:set main.some_option.s1 value
php cli.phar option:set --remote prod main.site_name 'Новый сайт'
```

## `option:delete [--remote=<codename>] [--local] <option>`

Удаляет опцию Bitrix. Формат имени такой же, как у `option:get`. Опция `--remote` выполняет удаление через административную PHP-консоль зарегистрированного удалённого проекта, а `--local` отключает неявный remote текущей сессии.

```bash
php cli.phar option:delete main.site_name
php cli.phar option:delete --remote prod main.site_name
```

## `setting:get [--remote=<codename>] [--local] [--extra] [--pretty] <path>`

Читает значение из `.settings.php` или, с `--extra`, из `.settings_extra.php`. Путь задаётся без корневого сегмента `value`.

```bash
php cli.phar setting:get connections.default.host
php cli.phar setting:get --pretty connections.default
php cli.phar setting:get --extra cache.type
```

## `setting:set [--remote=<codename>] [--local] [--extra] <path> <value>`

Изменяет `.settings.php` или `.settings_extra.php`. Значение декодируется как JSON, если это возможно.

```bash
php cli.phar setting:set cache.type '{"value":"memcache"}'
php cli.phar setting:set --extra custom.flag true
```

## `site:list [--remote=<codename>] [--local] [--filter=<json>] [--order=<json>] [--select=<json>] [--pretty] [--short[=<format>]] [--format=<table|csv>]`

Печатает сайты через `Bitrix\Main\SiteTable::getList`. По умолчанию выводит JSON-строки, с `--pretty` форматирует JSON. Опции `--short` и `--format` переключают вывод из JSON в строковый шаблон, консольную таблицу или CSV; они несовместимы между собой. В шаблоне `--short` подстановки задаются как `$FIELD`. Опции `filter`, `order`, `select` напрямую соответствуют параметрам D7. С `--remote` выполняет D7-операцию на зарегистрированном удалённом проекте через PHP-консоль админки; `--local` отключает remote текущей сессии.

```bash
php cli.phar site:list
php cli.phar site:list --filter='{"ACTIVE":"Y"}' --order='{"SORT":"ASC"}'
php cli.phar site:list --remote=prod --select='["LID","NAME"]'
php cli.phar site:list --pretty
php cli.phar site:list --short
php cli.phar site:list --short='[$LID] $NAME [$SERVER_NAME]'
php cli.phar site:list --format=table
php cli.phar site:list --format=csv
```

## `site:get [--remote=<codename>] [--local] [--select=<json>] <id>`

Печатает поля одного сайта по LID. С `--remote` получает сайт на зарегистрированном удалённом проекте через D7 `SiteTable`; `--local` отключает remote текущей сессии.

```bash
php cli.phar site:get s1
php cli.phar site:get --select='["LID","NAME","DIR"]' s1
php cli.phar site:get --remote=prod s1
```

## `site:add [--remote=<codename>] [--local] <fields-json>`

Добавляет сайт через D7 `SiteTable::add`. Поля передаются JSON-объектом с той же валидацией допустимых полей, что и в `site:update`. При успехе печатает ID созданной записи.

```bash
php cli.phar site:add '{"LID":"s2","NAME":"Новый сайт","DIR":"/"}'
php cli.phar site:add --remote=prod '{"LID":"s2","NAME":"Новый сайт"}'
```

## `site:delete [--remote=<codename>] [--local] <id>`

Удаляет сайт через D7 `SiteTable::delete`.

```bash
php cli.phar site:delete s2
php cli.phar site:delete --remote=prod s2
```

## `site:update [--remote=<codename>] [--local] <LID> <fields-json>`

Обновляет поля сайта через `SiteTable::update`. Второй аргумент должен быть JSON-объектом. С `--remote` валидирует JSON локально, а обновление выполняет на зарегистрированном удалённом проекте через D7; `--local` отключает remote текущей сессии.

```bash
php cli.phar site:update s1 '{"NAME":"Основной сайт"}'
php cli.phar site:update --remote=prod s1 '{"NAME":"Основной сайт"}'
```

## `iblock:add [--remote=<codename>] [--local] <fields-json>`

Добавляет инфоблок через `CIBlock::Add` и печатает ID созданного инфоблока. Поля передаются JSON-объектом; если аргумент не указан, JSON читается из stdin. Для удалённого проекта используйте `--remote`, а `--local` отключает неявный remote текущей сессии.

```bash
php cli.phar iblock:add '{"IBLOCK_TYPE_ID":"content","LID":"s1","NAME":"Новости","CODE":"news","ACTIVE":"Y"}'
echo '{"IBLOCK_TYPE_ID":"content","LID":["s1"],"NAME":"Каталог"}' | php cli.phar iblock:add
php cli.phar iblock:add --remote=prod '{"IBLOCK_TYPE_ID":"content","LID":"s1","NAME":"Новости"}'
```

## `iblock:get [--remote=<codename>] [--local] [--pretty] <ID>`

Печатает поля инфоблока как JSON через `CIBlock::GetList`. С `--pretty` форматирует JSON.

```bash
php cli.phar iblock:get 5
php cli.phar iblock:get --pretty 5
php cli.phar iblock:get --remote=prod 5
```

## `iblock:update [--remote=<codename>] [--local] <ID> <fields-json>`

Обновляет поля инфоблока через `CIBlock::Update`. Второй аргумент должен быть JSON-объектом и валидируется той же схемой допустимых полей, что и `iblock:add`.

```bash
php cli.phar iblock:update 5 '{"NAME":"Новые новости","ACTIVE":"Y"}'
php cli.phar iblock:update --remote=prod 5 '{"SORT":200}'
```

## `iblock:delete [--remote=<codename>] [--local] <ID>`

Удаляет инфоблок через `CIBlock::Delete`.

```bash
php cli.phar iblock:delete 5
php cli.phar iblock:delete --remote=prod 5
```

## `iblock.section:add [--remote=<codename>] [--local] <fields-json>`

Добавляет раздел инфоблока через `CIBlockSection::Add` и печатает ID. Поля передаются JSON-объектом или читаются из stdin. Схема валидации разрешает дополнительные поля, поэтому в payload можно передавать пользовательские поля `UF_*`.

```bash
php cli.phar iblock.section:add '{"IBLOCK_ID":5,"NAME":"Акции","CODE":"sale","UF_BADGE":"hot"}'
php cli.phar iblock.section:add --remote=prod '{"IBLOCK_ID":5,"NAME":"Акции"}'
```

## `iblock.section:get [--remote=<codename>] [--local] [--pretty] <ID>`

Печатает поля раздела как JSON. Команда сначала определяет `IBLOCK_ID` раздела, затем делает основную выборку `CIBlockSection::GetList` с `['*', 'UF_*']`, чтобы в ответ попали пользовательские поля. Тильда-ключи Bitrix (`~FIELD`) нормализуются в обычные имена полей.

```bash
php cli.phar iblock.section:get 10
php cli.phar iblock.section:get --pretty 10
php cli.phar iblock.section:get --remote=prod 10
```

## `iblock.section:update [--remote=<codename>] [--local] <ID> <fields-json>`

Обновляет раздел через `CIBlockSection::Update`. JSON-поля валидируются, дополнительные поля разрешены — это позволяет передавать пользовательские `UF_*` поля.

```bash
php cli.phar iblock.section:update 10 '{"NAME":"Скидки","UF_BADGE":"sale"}'
php cli.phar iblock.section:update --remote=prod 10 '{"SORT":100}'
```

## `iblock.section:delete [--remote=<codename>] [--local] <ID>`

Удаляет раздел через `CIBlockSection::Delete`.

```bash
php cli.phar iblock.section:delete 10
php cli.phar iblock.section:delete --remote=prod 10
```

## `iblock.type:add [--remote=<codename>] [--local] <fields-json>`

Добавляет тип инфоблока через `CIBlockType::Add` и печатает его строковый ID из поля `ID`. В payload обычно передают `ID`, `SECTIONS`, `IN_RSS`, `SORT` и `LANG`.

```bash
php cli.phar iblock.type:add '{"ID":"content","SECTIONS":"Y","IN_RSS":"N","SORT":100,"LANG":{"ru":{"NAME":"Контент","SECTION_NAME":"Разделы","ELEMENT_NAME":"Элементы"}}}'
php cli.phar iblock.type:add --remote=prod '{"ID":"content","SECTIONS":"Y","LANG":{"ru":{"NAME":"Контент"}}}'
```

## `iblock.type:get [--remote=<codename>] [--local] [--pretty] <ID>`

Печатает поля типа инфоблока как JSON через `CIBlockType::GetList`. ID типа — строка, например `content` или `catalog`.

```bash
php cli.phar iblock.type:get content
php cli.phar iblock.type:get --pretty content
php cli.phar iblock.type:get --remote=prod content
```

## `iblock.type:update [--remote=<codename>] [--local] <ID> <fields-json>`

Обновляет тип инфоблока через `CIBlockType::Update`.

```bash
php cli.phar iblock.type:update content '{"SORT":200,"LANG":{"ru":{"NAME":"Материалы"}}}'
php cli.phar iblock.type:update --remote=prod content '{"IN_RSS":"N"}'
```

## `iblock.type:delete [--remote=<codename>] [--local] <ID>`

Удаляет тип инфоблока через `CIBlockType::Delete`.

```bash
php cli.phar iblock.type:delete content
php cli.phar iblock.type:delete --remote=prod content
```

## `php:exec [--remote=<codename>] [--local]`

Читает PHP-код из stdin и выполняет его после подключения ядра Bitrix. Без `--remote` код исполняется локально; с `--remote` отправляется в зарегистрированный удалённый проект. `--local` отключает remote текущей сессии.

```bash
echo 'echo \Bitrix\Main\Context::getCurrent()->getServer()->getDocumentRoot();' | php cli.phar php:exec

echo 'echo "ok";' | php cli.phar php:exec --remote=prod
```
