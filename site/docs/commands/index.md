# Справочник команд

Команды сгруппированы по зонам ответственности. В исходном коде группы также отражают реорганизованные пространства имён команд: `Core` содержит системные и утилитарные команды, а `Bx` — команды, связанные с Bitrix-проектом и его данными.

## Bitrix

- `backup:list` — выводит основные файлы резервных копий из `/bitrix/backup`.
- `backup:get` — скачивает основной файл резервной копии и все его тома.
- `backup:extract` — распаковывает архив резервной копии Bitrix.
- `cache:clear` — очищает кеш Bitrix.
- `module:install`, `module:uninstall`, `module:reinstall`, `module:version` — управляют жизненным циклом модулей.
- `option:get`, `option:set` — читают и изменяют опции модулей.
- `setting:get`, `setting:set` — читают и изменяют `.settings.php` / `.settings_extra.php`.
- `site:add`, `site:delete`, `site:get`, `site:list`, `site:update` — работают с D7 `SiteTable`.
- `iblock:*`, `iblock.section:*`, `iblock.type:*` — управляют инфоблоками, разделами и типами инфоблоков через старый Bitrix API.
- `php:exec` — выполняет PHP-код в контексте Bitrix.

## База данных

- `db:exec` — выполняет SQL из stdin.
- `db:dump` — создаёт SQL-дамп.
- `db:apply` — применяет SQL-файл.
- `db:wipe` — очищает таблицы.

## CFile

- `cfile:save` — создаёт запись в `b_file` для существующего файла.
- `cfile:get` — возвращает JSON с результатом `CFile::GetFileArray()`.
- `cfile:delete` — удаляет файл из `b_file` через `CFile::Delete()`.

## Файлы

- `file:list` — выводит список файлов.
- `file:get` — скачивает файл.
- `file:put` — загружает файл на удалённый проект.
- `file:apply` — загружает директорию с проверкой конфликтов.
- `file:mkdir` — создаёт директорию.
- `file:delete` — удаляет файл.
- `file:rmdir` — удаляет директорию.

## Данные и системные утилиты

- `completion:bash` — генерирует bash completion-скрипт для выбранного имени исполняемого файла.
- `json:get`, `json:set` — читают и изменяют JSON из stdin.
- `env:get`, `env:set` — читают и изменяют dotenv-файлы.

## Remote

- `remote:register`, `remote:list`, `remote:delete` — управляют registry удалённых проектов.
- `session:remote` — печатает shell-команду для выбора remote в текущей терминальной сессии.
