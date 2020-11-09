# modern-bx/cli

Модульный консольный хелпер для автоматизации работы с Bitrix и другими фреймворками.

## Использование

```php cli.phar list``` выведет все доступные команды с описанием предназначения и возможных аргументов для каждой.

## Сборка

1.
    Установить [phive](https://github.com/phar-io/phive):

    ```
    wget -O phive.phar "https://phar.io/releases/phive.phar" \
        && wget -O phive.phar.asc "https://phar.io/releases/phive.phar.asc" \
        && gpg --keyserver hkps.pool.sks-keyservers.net --recv-keys 0x9D8A98B29B2D5D79 \
        && gpg --verify phive.phar.asc phive.phar \
        && rm phive.phar.asc \
        && chmod +x phive.phar \
        && sudo mv phive.phar /usr/local/bin/phive
    ```

2.
    Установить [box](https://github.com/box-project/box) с помощью `phive`:

    ```
    phive install
    ```

3.
    Собрать выполняемый файл:

    ```
    composer configure
    composer build
    ```

PHAR появится в `./dist`.

## Кастомизация

Система сборки позволяет собирать cli.phar только с теми наборами команд, который необходимы для конкретного проекта.

```
composer configure -- --bundle bundle1 bundle2 bundle3
```

Каждый бандл соответствует некоторому пространству имен в родительском пространстве `ModernBx/Cli/App/Console/Command`.

В разных бандлах могут содержаться команды с одинаковым кодом - в этом случае бандлы являются взаимоисключающими.
Состав бандлов, а также ревизия, на основе которой был собран файл, отображается при вызове справочной системы.  

## Лицензия

Apache 2.0.
