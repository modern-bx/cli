# Remote и сессии

Remote-команды управляют списком зарегистрированных удалённых Bitrix-проектов и выбором проекта для текущей shell-сессии.

## `remote:register <endpoint> [codename]`

Регистрирует удалённый проект. Команда авторизуется в админке, сохраняет endpoint, учётные данные и PHPSESSID. Если `codename` не передан, по умолчанию используется host endpoint. Если такой код уже занят или host не подходит под правила имени, имя генерируется автоматически.

```bash
php cli.phar remote:register https://example.org prod
```

Правила имени: латинские буквы в нижнем регистре, цифры, точки, подчёркивания и дефисы; имя должно начинаться с буквы или цифры.

## `remote:list`

Выводит список зарегистрированных проектов.

```bash
php cli.phar remote:list
```

## `remote:rename <prev> <next>`

Переименовывает зарегистрированный проект в локальном registry. Если `prev` не существует или `next` уже занят, команда завершается ошибкой.

```bash
php cli.phar remote:rename prod stage
```

## `remote:delete <codename>`

Удаляет зарегистрированный проект из локального registry.

```bash
php cli.phar remote:delete prod
```

## `session:remote [--unset] [remote]`

Печатает shell-команду для установки или сброса переменной окружения `BX_CLI_REMOTE` в текущей терминальной сессии. Чтобы команда повлияла на текущий shell, выполните её через `eval`.

```bash
eval "$(php cli.phar session:remote prod)"
eval "$(php cli.phar session:remote --unset)"
```

Если remote выбран в сессии, команды с поддержкой session remote автоматически применяют его без явной опции `--remote`.
