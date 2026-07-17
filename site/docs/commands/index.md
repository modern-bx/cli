# Справочник команд

Команды сгруппированы по зонам ответственности.

## Bitrix

- `cache:clear` — очищает кеш Bitrix.
- `module:install`, `module:uninstall`, `module:reinstall`, `module:version` — управляют жизненным циклом модулей.
- `option:get`, `option:set` — читают и изменяют опции модулей.
- `setting:get`, `setting:set` — читают и изменяют `.settings.php` / `.settings_extra.php`.
- `site:get`, `site:list`, `site:update` — работают с D7 `SiteTable`.
- `php:exec` — выполняет PHP-код в контексте Bitrix.

## База данных

- `db:exec` — выполняет SQL из stdin.
- `db:dump` — создаёт SQL-дамп.
- `db:apply` — применяет SQL-файл.
- `db:wipe` — очищает таблицы.

## Файлы

- `file:list` — выводит список файлов.
- `file:get` — скачивает файл.
- `file:put` — загружает файл на удалённый проект.
- `file:delete` — удаляет файл.

## Данные

- `json:get`, `json:set` — читают и изменяют JSON из stdin.
- `env:get`, `env:set` — читают и изменяют dotenv-файлы.

## Remote

- `remote:register`, `remote:list`, `remote:delete` — управляют registry удалённых проектов.
- `session:remote` — печатает shell-команду для выбора remote в текущей терминальной сессии.
