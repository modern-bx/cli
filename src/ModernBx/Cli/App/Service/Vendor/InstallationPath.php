<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Vendor;

final class InstallationPath
{
    public static function resolveForInstall(mixed $path, PackageStrategy $strategy): string
    {
        if ($path === null) {
            if (!$strategy->supportsDefaultPath()) {
                throw new \RuntimeException('Для пакета ' . $strategy->getName() . ' необходимо указать опцию --path.');
            }

            return '';
        }

        return self::normalize($path);
    }

    public static function resolveForUninstall(mixed $path): string
    {
        return $path === null ? '' : self::normalize($path);
    }

    private static function normalize(mixed $path): string
    {
        if (!is_string($path)) {
            throw new \RuntimeException('Опция --path должна быть строкой.');
        }

        $segments = [];
        foreach (explode('/', str_replace('\\', '/', trim($path))) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new \RuntimeException('Путь установки не должен выходить за document root.');
            }
            $segments[] = $segment;
        }

        return $segments === [] ? '' : '/' . implode('/', $segments);
    }
}
