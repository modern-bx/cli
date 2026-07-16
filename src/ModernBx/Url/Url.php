<?php

declare(strict_types=1);

namespace ModernBx\Url;

use function ModernBx\CommonFunctions\merge;

/**
 * Обертка для манипуляций с http-адресами. Мутабельная версия
 */
final class Url
{
    /**
     * Создает новый экземпляр на основе переданного $url.
     *
     * @param string $url
     * @return Url
     */
    public static function create(string $url = ""): Url
    {
        return new self($url);
    }

    /**
     * @var array<string, string|int>
     */
    private array $url;

    /**
     * @param array<string|int>|string $url
     */
    public function __construct(array|string $url = "")
    {
        if (is_array($url)) {
            $this->url = $url;
        } else {
            $this->url = parse_url($url) ?: [];
        }
    }

    /**
     * Устанавливает значение GET-параметра.
     *
     * @param string $param
     * @param string $value
     * @return Url
     */
    public function setQuery(string $param, string $value): Url
    {
        parse_str((string) $this->url["query"], $query);
        $query[$param] = $value;
        $this->url["query"] = http_build_query($query);

        return $this;
    }

    /**
     * Возвращает набор GET-параметров как ассоциативный массив.
     *
     * @param string $param
     * @return string|null
     */
    public function getQuery(string $param): ?string
    {
        parse_str((string) $this->url["query"], $query);

        /** @var string|null $queryValue */
        $queryValue = $query[$param] ?? null;

        return $queryValue;
    }

    /**
     * Удаляет GET-параметр.
     *
     * @param string $param
     * @return Url
     */
    public function removeQuery(string $param): Url
    {
        parse_str((string) $this->url["query"], $query);
        unset($query[$param]);
        $this->url["query"] = http_build_query($query);

        return $this;
    }

    /**
     * Устанавливает значения нескольких GET-параметров.
     *
     * @param array<string, string|int> $map
     * @return Url
     */
    public function setQueryMap(array $map): Url
    {
        parse_str((string) $this->url["query"], $query);
        merge($query, $map);
        $this->url["query"] = http_build_query($query);

        return $this;
    }

    /**
     * Возвращает URL как строку.
     *
     * @return string
     */
    public function __toString(): string
    {
        return http_build_url($this->url);
    }

    /**
     * @return string|null
     */
    public function getHost(): ?string
    {
        return isset($this->url["host"]) ? (string) $this->url["host"] : null;
    }

    /**
     * @return string|null
     */
    public function getScheme(): ?string
    {
        return isset($this->url["scheme"]) ? (string) $this->url["scheme"] : null;
    }

    /**
     * @return string|null
     */
    public function getPath(): ?string
    {
        return isset($this->url["path"]) ? (string) $this->url["path"] : null;
    }

    /**
     * @return int|null
     */
    public function getPort(): ?int
    {
        return isset($this->url["port"]) ? (int) $this->url["port"] : null;
    }

    /**
     * Устанавливает хост-имя.
     *
     * @param string $host
     * @return Url
     */
    public function setHost(string $host): Url
    {
        $this->url["host"] = $host;

        return $this;
    }

    /**
     * Устанавливает путь.
     *
     * @param string $path
     * @return Url
     */
    public function setPath(string $path): Url
    {
        $this->url["path"] = $path;

        return $this;
    }

    /**
     * Устанавливает протокол.
     *
     * @param string $scheme
     * @return Url
     */
    public function setScheme(string $scheme): Url
    {
        $this->url["scheme"] = $scheme;

        return $this;
    }

    /**
     * Устанавливает протокол.
     *
     * @param int $port
     * @return Url
     */
    public function setPort(int $port): Url
    {
        $this->url["port"] = $port;

        return $this;
    }

    /**
     * Возвращает путь в виде массива директорий.
     *
     * @return array<string>
     */
    public function getPathSegments(): array
    {
        return array_values(array_filter(explode("/", (string) $this->getPath())));
    }

    /**
     * Устанавливает путь. Принимает массив директорий.
     *
     * @param array<string> $segments
     * @param bool $trailingSlash
     * @return Url
     */
    public function setPathSegments(array $segments, bool $trailingSlash = true): Url
    {
        $segments = array_filter($segments);
        $this->url["path"] = "/" . join("/", $segments);

        $isFile = false;
        if (function_exists("str_ends_with")) {
            if (str_ends_with($this->url["path"], ".php") || str_ends_with($this->url["path"], ".html")) {
                $isFile = true;
            }
        }
        
        if ($trailingSlash && $segments && !$isFile) {
            $this->url["path"] .= "/";
        }

        return $this;
    }

    /**
     * Удаляет из пути первый сегмент.
     *
     * @return Url
     */
    public function shiftPathSegment(): Url
    {
        $segments = $this->getPathSegments();
        array_shift($segments);

        return $this->setPathSegments($segments);
    }

    /**
     * Удаляет из пути первый сегмент.
     *
     * @return Url
     */
    public function shift(): Url
    {
        return $this->shiftPathSegment();
    }

    /**
     * Добавляет в начало пути новый сегмент.
     *
     * @param string $segment
     * @param bool $trailingSlash
     * @return Url
     */
    public function unshiftPathSegment(string $segment, bool $trailingSlash = true): Url
    {
        $segments = $this->getPathSegments();
        array_unshift($segments, $segment);

        return $this->setPathSegments($segments, $trailingSlash);
    }

    /**
     * Добавляет в начало пути новый сегмент.
     *
     * @param string $segment
     * @return Url
     */
    public function unshift(string $segment): Url
    {
        return $this->unshiftPathSegment($segment);
    }

    /**
     * Добавляет в конец пути сегмент.
     *
     * @param string $segment
     * @return Url
     */
    public function pushPathSegment(string $segment): Url
    {
        $segments = $this->getPathSegments();
        $segments[] = $segment;

        return $this->setPathSegments($segments);
    }

    /**
     * Добавляет в конец пути сегмент.
     *
     * @param string $segment
     * @return Url
     */
    public function push(string $segment): Url
    {
        return $this->pushPathSegment($segment);
    }

    /**
     * Удаляет последний сегмент пути.
     *
     * @return Url
     */
    public function popPathSegment(): Url
    {
        $segments = $this->getPathSegments();
        array_pop($segments);

        return $this->setPathSegments($segments);
    }

    /**
     * Удаляет последний сегмент пути.
     *
     * @return Url
     */
    public function pop(): Url
    {
        return $this->popPathSegment();
    }

    /**
     * Возвращает URL как строку.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->__toString();
    }

    /**
     * Добавляет в конец пути слеш, если его там нет.
     *
     * @return Url
     */
    public function withTrailingSlash(): Url
    {
        return $this->setPathSegments($this->getPathSegments());
    }

    /**
     * Удаляет из конца пути слеш, если он там есть нет.
     *
     * @return Url
     */
    public function withoutTrailingSlash(): Url
    {
        return $this->setPathSegments($this->getPathSegments(), false);
    }
}
