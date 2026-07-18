# modern-bx/cli

`modern-bx/cli` — модульный консольный помощник для автоматизации рутинных операций в проектах на Bitrix: чтение и изменение настроек, управление модулями, очистка кеша, операции с базой данных, файлами и удалёнными инсталляциями.

## Основная идея

CLI собирается в PHAR и запускает Symfony Console-приложение. Команды загружаются динамически из пространства имён `ModernBx\Cli\App\Console\Command`, где после реорганизации верхний уровень разделён на `Core` и `Bx`. `Core` содержит команды, не зависящие от Bitrix-проекта: remote registry, session remote, JSON/dotenv и shell completion. `Bx` содержит команды, работающие с Bitrix-инсталляцией, базой, файлами, сайтами, модулями и резервными копиями. Большая часть Bitrix-команд сначала определяет document root и каталог `bitrix`, подключает ядро, а затем выполняет прикладную операцию.

## Быстрый старт

```bash
php cli.phar list
php cli.phar cache:clear
php cli.phar option:get main.site_name
php cli.phar backup:extract ./backup.tar.gz ./restore
php cli.phar completion:bash bx-cli
```

Для подробностей см. разделы:

- [Установка и сборка](./guide/install.md)
- [Архитектура и общая логика](./guide/architecture.md)
- [Справочник команд](./commands/index.md)
