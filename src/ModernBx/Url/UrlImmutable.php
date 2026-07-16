<?php

declare(strict_types=1);

namespace ModernBx\Url;

use function ModernBx\CommonFunctions\merge;

/**
 * Обертка для манипуляций с http-адресами. Иммутабельная версия
 */
final class UrlImmutable
{
    /**
     * Создает новый экземпляр на основе переданного $url.
     *
     * @param string $url
     * @return UrlImmutable
     */
    public static function create(string $url = ""): UrlImmutable
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
     * @return UrlImmutable
     */
    public function setQuery(string $param, string $value): UrlImmutable
    {
        $url = $this->url;

        parse_str((string) $url["query"], $query);
        $query[$param] = $value;
        $url["query"] = http_build_query($query);

        return new self($url);
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
     * @return UrlImmutable
     */
    public function removeQuery(string $param): UrlImmutable
    {
        $url = $this->url;

        parse_str((string) $url["query"], $query);
        unset($query[$param]);
        $url["query"] = http_build_query($query);

        return new self($url);
    }

    /**
     * Устанавливает значения нескольких GET-параметров.
     *
     * @param array<string, string|int> $map
     * @return UrlImmutable
     */
    public function setQueryMap(array $map): UrlImmutable
    {
        $url = $this->url;

        parse_str((string) $url["query"], $query);
        merge($query, $map);
        $url["query"] = http_build_query($query);

        return new self($url);
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
     * @return UrlImmutable
     */
    public function setHost(string $host): UrlImmutable
    {
        $url = $this->url;
        $url["host"] = $host;

        return new self($url);
    }

    /**
     * Устанавливает путь.
     *
     * @param string $path
     * @return UrlImmutable
     */
    public function setPath(string $path): UrlImmutable
    {
        $url = $this->url;
        $url["path"] = $path;

        return new self($url);
    }

    /**
     * Устанавливает протокол.
     *
     * @param string $scheme
     * @return UrlImmutable
     */
    public function setScheme(string $scheme): UrlImmutable
    {
        $url = $this->url;
        $url["scheme"] = $scheme;

        return new self($url);
    }

    /**
     * Устанавливает протокол.
     *
     * @param int $port
     * @return UrlImmutable
     */
    public function setPort(int $port): UrlImmutable
    {
        $url = $this->url;
        $url["port"] = $port;

        return new self($url);
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
     * @return UrlImmutable
     */
    public function setPathSegments(array $segments, bool $trailingSlash = true): UrlImmutable
    {
        $url = $this->url;
        $segments = array_filter($segments);
        $url["path"] = "/" . join("/", $segments);

        $isFile = false;
        if (function_exists("str_ends_with")) {
            if (str_ends_with($url["path"], ".php") || str_ends_with($url["path"], ".html")) {
                $isFile = true;
            }
        }
        
        if ($trailingSlash && $segments && !$isFile) {
            $url["path"] .= "/";
        }

        return new self($url);
    }

    /**
     * Удаляет из пути первый сегмент.
     *
     * @return UrlImmutable
     */
    public function shiftPathSegment(): UrlImmutable
    {
        $segments = $this->getPathSegments();
        array_shift($segments);

        return $this->setPathSegments($segments);
    }

    /**
     * Удаляет из пути первый сегмент.
     *
     * @return UrlImmutable
     */
    public function shift(): UrlImmutable
    {
        return $this->shiftPathSegment();
    }

    /**
     * Добавляет в начало пути новый сегмент.
     *
     * @param string $segment
     * @param bool $trailingSlash
     * @return UrlImmutable
     */
    public function unshiftPathSegment(string $segment, bool $trailingSlash = true): UrlImmutable
    {
        $segments = $this->getPathSegments();
        array_unshift($segments, $segment);

        return $this->setPathSegments($segments, $trailingSlash);
    }

    /**
     * Добавляет в начало пути новый сегмент.
     *
     * @param string $segment
     * @return UrlImmutable
     */
    public function unshift(string $segment): UrlImmutable
    {
        return $this->unshiftPathSegment($segment);
    }

    /**
     * Добавляет в конец пути сегмент.
     *
     * @param string $segment
     * @return UrlImmutable
     */
    public function pushPathSegment(string $segment): UrlImmutable
    {
        $segments = $this->getPathSegments();
        $segments[] = $segment;

        return $this->setPathSegments($segments);
    }

    /**
     * Добавляет в конец пути сегмент.
     *
     * @param string $segment
     * @return UrlImmutable
     */
    public function push(string $segment): UrlImmutable
    {
        return $this->pushPathSegment($segment);
    }

    /**
     * Удаляет последний сегмент пути.
     *
     * @return UrlImmutable
     */
    public function popPathSegment(): UrlImmutable
    {
        $segments = $this->getPathSegments();
        array_pop($segments);

        return $this->setPathSegments($segments);
    }

    /**
     * Удаляет последний сегмент пути.
     *
     * @return UrlImmutable
     */
    public function pop(): UrlImmutable
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
     * @return UrlImmutable
     */
    public function withTrailingSlash(): UrlImmutable
    {
        return $this->setPathSegments($this->getPathSegments());
    }

    /**
     * Удаляет из конца пути слеш, если он там есть нет.
     *
     * @return UrlImmutable
     */
    public function withoutTrailingSlash(): UrlImmutable
    {
        return $this->setPathSegments($this->getPathSegments(), false);
    }
}
