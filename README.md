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

## Bash autocompletion

Если PHAR установлен как `~/.local/bin/bx-cli` и имеет права на выполнение, сгенерируйте completion-скрипт и подключите его в текущей оболочке:

```bash
mkdir -p ~/.local/share/bash-completion/completions
bx-cli completion:bash bx-cli > ~/.local/share/bash-completion/completions/bx-cli
source ~/.local/share/bash-completion/completions/bx-cli
```

После перезапуска bash completion подхватится автоматически, если пакет `bash-completion` установлен и подключается вашими shell-настройками.

Если PHAR доступен под другим именем, передайте это имя аргументом команды `completion:bash` и сохраните файл completion под тем же именем.

## Кастомизация

Система сборки позволяет собирать cli.phar только с теми наборами команд, который необходимы для конкретного проекта.

```
composer configure -- --bundle bundle1 bundle2 bundle3
```

Каждый бандл соответствует некоторому пространству имен в родительском пространстве `ModernBx/Cli/App/Console/Command`.

В разных бандлах могут содержаться команды с одинаковым кодом - в этом случае бандлы являются взаимоисключающими.
Состав бандлов, а также ревизия, на основе которой был собран файл, отображается при вызове справочной системы.  

## Документация

Документация проекта находится в каталоге `site` и собирается с помощью VitePress.

```bash
cd site
npm install
npm run build
npm run preview
```

После `npm run preview` VitePress выведет локальный URL для просмотра собранной документации. Для режима разработки используйте `npm run dev`.

### Публикация документации на GitHub Pages

В репозитории добавлен workflow `.github/workflows/docs-pages.yml`, который собирает `site` и публикует статическую сборку на GitHub Pages. Публикация запускается только если в GitHub Actions Variables задана переменная `USE_GITHUB_PAGES` с непустым значением.

Для кастомного домена можно дополнительно задать Actions Variable `GITHUB_PAGES_CNAME` со значением домена, например `docs.example.com`; workflow добавит файл `CNAME` в артефакт Pages.

## Лицензия

Apache 2.0.
