<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

declare(strict_types=1);

namespace ModernBx\CommonFunctions;

use const ModernBx\CommonFunctions\Constants\Attr\ATTR_PATTERN_DATA;
use const ModernBx\CommonFunctions\Constants\Attr\ATTR_PATTERN_STD;

if (!function_exists("classlist")) {
    /**
     * Преобразует массив CSS-классов в строку по следующему правилу:
     *
     * <code>
     * ["b-user", "b-user--logged" => true, "b-user--admin" => false] => "b-user b-user--logged"
     * </code>
     *
     * @param array<int|string, string|bool> $map
     * @return string
     */
    function classlist(array $map = []): string
    {
        $classes = [];

        foreach ($map as $key => $value) {
            if (is_int($key)) {
                $classes[] = (string) $value;
            } elseif ($value) {
                $classes[] = $key;
            }
        }

        return implode(" ", $classes);
    }
}

if (!function_exists("classnames")) {
    /**
     * Алиас для classlist().
     *
     * @param array<int|string, string|bool> $map
     * @return string
     * @see \classlist()
     *
     */
    function classnames(array $map = []): string
    {
        return classlist($map);
    }
}

if (!function_exists("with")) {
    /**
     * @template T
     * @param T $object
     * @return T|null
     */
    function with(mixed $object): mixed
    {
        return $object;
    }
}

if (!function_exists("file_ext")) {
    /**
     * Возвращает расширение файла на основе его имени.
     *
     * @param string $filename
     * @return string|null
     */
    function file_ext(string $filename): ?string
    {
        if (!str_contains($filename, ".")) {
            return null;
        }

        return last_of(explode(".", $filename));
    }
}

if (!function_exists("format")) {
    /**
     * Заменяет именованные переменные в строке на значения.
     *
     * Используется синтаксис format из Python ("{key}"). Сами ключи передаются без скобок.
     *
     * <code>
     * format("hello, {who}", ['who' => 'world']); // hello, world
     * </code>
     *
     * @param string $message
     * @param array<string, string|int> $pairs
     * @return string
     */
    function format(string $message, array $pairs = []): string
    {
        return str_replace(array_map(function ($key) {
            return sprintf("{%s}", $key);
        }, array_keys($pairs)), array_values($pairs), $message);
    }
}

if (!function_exists("array_exclude_keys")) {
    /**
     * Возвращает копию $arSrc, из которого были удалены все ключи из $arPaths.
     *
     * <code>
     * $export = array_exclude_keys($arResult, ["PROPERTIES", "PRICES"]);
     * </code>
     *
     * @param array<mixed> $source
     * @param array<mixed> $paths
     * @return array<mixed>
     */
    function array_exclude_keys(array $source, array $paths): array
    {
        foreach ($paths as $path) {
            if (!is_array($path)) {
                $path = [$path];
            }

            $subSrc = &$source;
            $length = count($path);

            // Совершенно бессмысленная проверка, чтобы PHPStan успокоился
            // TODO: Обойти по-другому, предпочтительно через аннотации
            if (!is_array($subSrc)) {
                continue;
            }

            foreach ($path as $index => $key) {
                if (!array_key_exists($key, $subSrc)) {
                    break;
                } elseif ($index == $length - 1) {
                    unset($subSrc[$key]);
                    break;
                }

                $subSrc = &$subSrc[$key];
            }

            unset($subSrc);
        }

        return $source;
    }
}

if (!function_exists("merge")) {
    /**
     * Записывает данные массивов-источников в массив-получатель.
     *
     * Работает только с ассоциативными массивами.
     *
     * <code>
     * merge($arResult, $props, $prices, $rests);
     * </code>
     *
     * @param array<mixed> $arDest
     * @param array<mixed> $arSrc
     */
    function merge(array &$arDest, array ...$arSrc): void
    {
        foreach ($arSrc as $ary) {
            foreach ($ary as $key => $value) {
                $arDest[$key] = $value;
            }
        }
    }
}

if (!function_exists("preg_first")) {
    /**
     * Возвращает первое совпадение строки c регулярным выражением или `false`, если совпадений нет.
     *
     * @param string $pattern
     * @param string $s
     * @return string|bool
     */
    function preg_first(string $pattern, string $s)
    {
        if (preg_match($pattern, $s, $matches)) {
            return $matches[0];
        }

        return false;
    }
}

if (!function_exists("get_invalid_json_paths")) {
    /**
     * Рекурсивно проверяет массив на предмет наличия невалидных для формата JSON PHP-значений
     * (например, INF или строки в формате, отличном от UTF-8).
     *
     * Возвращаем массив со списком путей к таким значениям (в виде последовательности ключей).
     *
     * @param array<mixed> $input
     * @return array<mixed>
     */
    function get_invalid_json_paths(array $input): array
    {
        $walker = function (array $input, array &$acc = [], array $stack = []) use (&$walker): array {
            foreach ($input as $key => $value) {
                if (json_encode($value) === false) {
                    if (is_array($value)) {
                        $walker($value, $acc, array_merge($stack, [$key]));
                    } else {
                        $acc[] = array_merge($stack, [$key]);
                    }
                }
            }

            return $acc;
        };

        return $walker($input);
    }
}

if (!function_exists("deep_set")) {
    /**
     * Функция устанавливает значение в многомерном ассоциативном массиве по указанному пути.
     * Если $immutable равно false, то устанавливает значение в многомерном ассоциативном массиве
     * по указанному пути, не возвращая значения.
     * Иначе, возвращает модифицированную копию массива, не изменяя аргумент.
     *
     * <code>
     * deep_set($arResult, ["PROPERTIES", "RADIATION_LEVEL", "VALUE"], "Pretty terrible.");
     * </code>
     *
     * @param array<mixed> $ary
     * @param array<string>|string $keys
     * @param mixed $value
     * @param bool $immutable
     * @return array<mixed>|null
     */
    function deep_set(array &$ary, array|string $keys, mixed $value, bool $immutable = false): ?array
    {
        if (is_string($keys)) {
            $keys = explode(".", $keys);
        }

        $counter = 0;
        if ($immutable) {
            $aryCopy = $ary;
            $pointer = &$aryCopy;
        } else {
            $pointer = &$ary;
        }

        // Совершенно бессмысленная проверка, чтобы PHPStan успокоился
        // TODO: Обойти по-другому, предпочтительно через аннотации
        if (!is_array($pointer)) {
            return null;
        }

        while ($counter < count($keys) - 1) {
            $pointer = &$pointer[$keys[$counter++]];
        }

        if ($keys) {
            $pointer[$keys[$counter]] = $value;
        } else {
            $pointer = $value;
        }

        if ($immutable) {
            return $aryCopy;
        }

        return null;
    }
}

if (!function_exists("deep_get")) {
    /**
     * Функция извлекает значение из многомерного ассоциативного массива по указанному пути.
     *
     * <code>
     * echo deep_get($arResult, ["PROPERTIES", "RADIATION_LEVEL", "VALUE"], "Not great, not terrible.");
     * </code>
     *
     * @param array<mixed> $ary
     * @param array<string>|string $keys
     * @param mixed $defaultValue
     * @return mixed
     */
    function deep_get(array $ary, array|string $keys, mixed $defaultValue = null): mixed
    {
        if (is_string($keys)) {
            $keys = explode(".", $keys);
        }

        $counter = 0;
        $result = &$ary;

        // Совершенно бессмысленная проверка, чтобы PHPStan успокоился
        // TODO: Обойти по-другому, предпочтительно через аннотации
        if (!is_array($result)) {
            return null;
        }

        while ($counter < count($keys)) {
            if (array_key_exists($keys[$counter], $result)) {
                $result = &$result[$keys[$counter++]];
            } else {
                return $defaultValue;
            }
        }

        return $result;
    }
}

if (!function_exists("to_json")) {
    /**
     * Обертка над json_encode.().
     *
     * Не экранирует UTF-8 и (в режиме отладки) форматирует JSON.
     *
     * @param mixed $value
     * @param int|null $flags
     * @return string|false
     * @throws \JsonException
     */
    function to_json(mixed $value, ?int $flags = null): string|false
    {
        if (!isset($flags)) {
            $flags = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
        }

        if (defined("JSON_THROW_ON_ERROR")) {
            $flags = $flags | JSON_THROW_ON_ERROR;
        }

        $result = json_encode($value, $flags);

        if (!defined("JSON_THROW_ON_ERROR") && json_last_error()) {
            throw new \JsonException(json_last_error_msg());
        }

        return $result;
    }
}

if (!function_exists("from_json")) {
    /**
     * Обертка над json_decode().
     *
     * Преобразует JSON-строку в стандартный массив.
     *
     * @param string $value
     * @param int $flags
     * @return mixed
     * @throws \JsonException
     * @throws \RuntimeException
     */
    function from_json(string $value, int $flags = 0): mixed
    {
        if (defined("JSON_THROW_ON_ERROR")) {
            $flags = $flags | JSON_THROW_ON_ERROR;
        }

        $result = json_decode($value, true, 512, $flags);

        if (!defined("JSON_THROW_ON_ERROR") && json_last_error()) {
            throw new \JsonException(json_last_error_msg());
        }

        return $result;
    }
}

if (!function_exists("attrs")) {
    /**
     * Развертывает массив произвольных атрибутов и их значений в строку.
     *
     * @param array<string, string|int|false|null> $attrs Ассоциативный массив атрибутов (ключ-значение)
     * @param string|callable $pattern Паттерн развертки
     * @return string
     */
    function attrs(array $attrs, string|callable $pattern = ATTR_PATTERN_STD): string
    {
        $result = "";
        $counter = 0;

        foreach ($attrs as $key => $value) {
            if ($value === false) {
                continue;
            }

            $pair = false;

            if (is_callable($pattern)) {
                $pair .= $pattern($key, $value);
            } else {
                $pair = format($pattern, [
                    "key" => $key,
                    "value" => htmlspecialchars((string) $value, ENT_QUOTES),
                    "raw_value" => (string) $value,
                ]);
            }

            if ($counter > 0) {
                $result .= " ";
            }

            $result .= $pair;

            $counter++;
        }

        return $result;
    }
}

if (!function_exists("data_attrs")) {
    /**
     * Развертывает массив дата-атрибутов и их значений в строку.
     *
     * @param array<string, string|int|false|null> $attrs Ассоциативный массив атрибутов (ключ-значение)
     * @return string
     */
    function data_attrs(array $attrs): string
    {
        return attrs($attrs, ATTR_PATTERN_DATA);
    }
}


if (!function_exists("first_key_of")) {
    /**
     * Возвращает первый ключ массива.
     *
     * @param array<mixed> $ary
     * @return mixed
     *
     */
    function first_key_of(array $ary): mixed
    {
        if (!$ary) {
            return null;
        }

        $keys = array_keys($ary);

        if (!isset($keys[0])) {
            return null;
        }

        return $keys[0];
    }
}

if (!function_exists("first_of")) {
    /**
     * Возвращает первый элемент массива.
     *
     * @param array<mixed> $ary
     * @return mixed
     *
     */
    function first_of(array $ary): mixed
    {
        if (!$ary) {
            return null;
        }

        $key = first_key_of($ary);

        if (!isset($key) || !isset($ary[$key])) {
            return null;
        }

        return $ary[$key];
    }
}

if (!function_exists("last_key_of")) {
    /**
     * Возвращает последний ключ массива.
     *
     * @param array<mixed> $ary
     * @return mixed
     *
     */
    function last_key_of(array $ary): mixed
    {
        if (!$ary) {
            return null;
        }

        $keys = array_keys($ary);

        return $keys[count($keys) - 1];
    }
}

if (!function_exists("last_of")) {
    /**
     * Возвращает последний элемент массива.
     *
     * @template T
     * @param array<T>|null $ary
     * @return T|null
     *
     */
    function last_of(?array $ary): mixed
    {
        if (!$ary) {
            return null;
        }

        $key = last_key_of($ary);

        if (!isset($key) || !isset($ary[$key])) {
            return null;
        }

        return $ary[$key];
    }
}

if (!function_exists("immutable_sort")) {
    /**
     * Адаптер для стандартных фунций сортировки, который возвращает значение отсортированного массива,
     * а не изменяет аргумент.
     *
     * <code>
     * $sorted = immutable_sort($ids, "uksort", fn ($a, $b) => $a - $b);
     * </code>
     *
     * @param array<mixed> $ary
     * @param callable|null $function
     * @param array<mixed> $rest
     * @return array<mixed>
     */
    function immutable_sort(array $ary, ?callable $function = null, ...$rest): array
    {
        if (!$function) {
            $function = "sort";
        }

        $function($ary, ...$rest);

        return $ary;
    }
}

if (!function_exists("index")) {
    /**
     * Используется для индексации элементов-массивов по некому критерию.
     *
     * Индексатор может быть строкой (ключом), массивом строк (путь, который будет передан в deep_get()), или функцией.
     *
     * @param array<mixed> $item
     * @param int|string|array<mixed>|callable $index
     * @return mixed
     * @throws \Exception
     */
    function index(array $item, int|string|array|callable $index): mixed
    {
        switch (true) {
            case is_string($index):
                $key = $item[$index] ?? null;
                break;
            case is_array($index) && $index:
                $key = deep_get($item, array_values($index));
                if (!is_scalar($key)) {
                    $key = to_json($key, JSON_UNESCAPED_UNICODE);
                }
                break;
            case is_callable($index):
                $key = call_user_func($index, $item);
                break;
            default:
                throw new \InvalidArgumentException("Некорректный тип или значение индексатора.");
        }

        return $key;
    }
}

if (!function_exists("is_map")) {
    /**
     * Проверяет, является ли массив ассоциативным.
     *
     * @param array<mixed> $value
     * @return bool
     */
    function is_map(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}

if (!function_exists("capture")) {
    /**
     * Выполняет функцию, захватывая ее вывод в буфер, и возвращает его как строку.
     *
     * @param callable $fn
     * @return string|bool
     */
    function capture(callable $fn): string|bool
    {
        ob_start();

        $fn();

        return ob_get_clean();
    }
}
