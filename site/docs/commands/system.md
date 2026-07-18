# Системные команды

Системные команды относятся к базовому ядру CLI и не требуют Bitrix-проекта. Они полезны для настройки окружения, shell-интеграций и скриптов, которые запускаются рядом с командами Bitrix.

## `completion:bash [executable]`

Печатает bash completion-скрипт для указанного имени исполняемого файла. Если аргумент не передан, в скрипте используется имя `bx-cli`.

```bash
php cli.phar completion:bash bx-cli > ~/.local/share/bash-completion/completions/bx-cli
source ~/.local/share/bash-completion/completions/bx-cli
```

Скрипт получает список команд через `list --raw`, а опции конкретной команды — из `help <command> --format=txt`, поэтому completion автоматически учитывает команды, попавшие в текущую сборку PHAR.
