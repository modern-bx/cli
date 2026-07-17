# JSON и dotenv

Эти команды не требуют Bitrix-проекта и полезны в shell-скриптах.

## `json:get [--pretty] <path>`

Читает JSON из stdin и печатает значение по dot-separated пути. Пустой путь возвращает весь документ. Точки внутри ключей экранируются обратным слешем.

```bash
echo '{"site":{"name":"Demo"}}' | php cli.phar json:get site.name

echo '{"a.b":{"c":1}}' | php cli.phar json:get 'a\.b.c'
```

## `json:set [--pretty] <path> <value>`

Читает JSON из stdin, декодирует `value` как JSON, устанавливает значение по пути и печатает итоговый документ. Пустой путь заменяет весь ввод.

```bash
echo '{"site":{"name":"Demo"}}' | php cli.phar json:set site.name '"Production"'

echo '{}' | php cli.phar json:set --pretty features.enabled true
```

## `env:get <file> <key>`

Читает dotenv-файл и печатает декодированное значение переменной.

```bash
php cli.phar env:get .env DB_HOST
```

## `env:set <file> <key> <value>`

Обновляет или добавляет переменную в dotenv-файл и печатает итоговое содержимое.

```bash
php cli.phar env:set .env APP_ENV production
php cli.phar env:set .env FEATURE_FLAG true
```
