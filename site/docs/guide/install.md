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
composer configure -- --bundle Bx Db File Remote
composer build
```

Бандл соответствует подпространству имён внутри `ModernBx/Cli/App/Console/Command`. Например, `Bx` включает команды Bitrix, `Db` — команды базы данных. Если в нескольких бандлах есть команды с одинаковым именем, такие бандлы конфликтуют.

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
