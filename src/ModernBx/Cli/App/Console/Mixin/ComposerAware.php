<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Mixin;

trait ComposerAware
{
    /**
     * Composer не загружает файлы из раздела autoload.files в composer.json, если такой файл уже был ранее загружен
     * предыдущим экземпляром загрузчика классов.
     * Проверка использует глобальную переменную $__composer_autoload_files, хранящую массив вида 'хеш файла' => true.
     *
     * В нашем случае такое поведение недопустимо, т.к. и Phar, и сайт могут использовать пакеты,
     * экспортирующую в глобальное пространство имен свои функции. Эти функции при подключении пролога из Phar будут
     * недоступны коду сайта, т.к. в ходе компиляции архива были завернуты через PHP-Scoper в некое пространство
     * имен и перестали быть глобальными.
     *
     * Повлиять на поведение Composer мы не можем, вклиниться в процесс компиляции Box - тоже, поэтому перед
     * подключением пролога мы изменяем хеши загруженных файлов в $__composer_autoload_files, чтобы они были
     * снова загружены.
     */
    protected function concealAutoloadFiles(): void
    {
        global $__composer_autoload_files;

        $__composer_autoload_files = array_reduce(
            array_keys($__composer_autoload_files),
            function ($acc, $k) use ($__composer_autoload_files) {
                return array_merge($acc, [md5((string) $k) => $__composer_autoload_files[$k]]);
            },
            [],
        );
    }
}
