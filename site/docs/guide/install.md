# Установка и сборка

## Требования

- PHP 8.1 или выше.
- Composer.
- Расширения PHP: `json`, `mbstring`.
- Для сборки PHAR — `box`, устанавливаемый через `phive`.

## Сборка PHAR

```bash
phive install
composer configure
composer build
```

Готовый PHAR появляется в каталоге `dist`.

## Сборка с выбранными бандлами

```bash
composer configure -- --bundle Core Bx
composer build
```

Бандл соответствует подпространству имён внутри `ModernBx/Cli/App/Console/Command`. После реорганизации основных неймспейсов используйте верхнеуровневые бандлы `Core` и `Bx`: `Core` включает системные утилиты (`remote:*`, `session:remote`, `json:*`, `env:*`, `completion:bash`), а `Bx` — команды Bitrix-проекта (`cache:*`, `module:*`, `option:*`, `setting:*`, `site:*`, `db:*`, `file:*`, `php:exec`, `backup:*`). Если в нескольких бандлах есть команды с одинаковым именем, такие бандлы конфликтуют.

## Документация VitePress

Документация находится в папке `site` и содержит отдельную систему сборки.

```bash
cd site
npm install
npm run build
npm run preview
```

Для разработки используйте:

```bash
cd site
npm install
npm run dev
```
