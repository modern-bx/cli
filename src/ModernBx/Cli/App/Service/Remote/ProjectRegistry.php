<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Service\Remote;

use Symfony\Component\Yaml\Yaml;

final class ProjectRegistry
{
    public function getProjectsDir(): string
    {
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? null);

        if (!$home) {
            throw new \RuntimeException('Не удалось определить домашний каталог пользователя.');
        }

        return rtrim($home, '/') . '/.config/bx-cli/projects';
    }

    public function getConfigFile(string $codename): string
    {
        return $this->getProjectsDir() . '/' . $codename . '/project.yaml';
    }

    public function exists(string $codename): bool
    {
        return is_file($this->getConfigFile($codename));
    }

    /**
     * @return string[]
     */
    public function list(): array
    {
        $projectsDir = $this->getProjectsDir();

        if (!is_dir($projectsDir)) {
            return [];
        }

        $projects = [];
        $items = scandir($projectsDir);

        if ($items === false) {
            throw new \RuntimeException('Не удалось прочитать каталог проектов.');
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_file($projectsDir . '/' . $item . '/project.yaml')) {
                $projects[] = $item;
            }
        }

        sort($projects);

        return $projects;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $codename): array
    {
        $file = $this->getConfigFile($codename);

        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('Проект не зарегистрирован: %s', $codename));
        }

        $config = Yaml::parseFile($file);

        if (!is_array($config)) {
            throw new \RuntimeException(sprintf('Некорректная конфигурация проекта: %s', $codename));
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function save(string $codename, array $config): void
    {
        $file = $this->getConfigFile($codename);
        $dir = dirname($file);

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Не удалось создать каталог конфигурации: %s', $dir));
        }

        if (file_put_contents($file, Yaml::dump($config, 8, 2)) === false) {
            throw new \RuntimeException(sprintf('Не удалось сохранить конфигурацию: %s', $file));
        }

        chmod($file, 0600);
    }


    public function rename(string $prev, string $next): void
    {
        if (!$this->isValidCodename($next)) {
            throw new \RuntimeException(
                'Кодовое имя проекта должно содержать только латинские буквы, цифры, точки, дефисы и подчеркивания.',
            );
        }

        if (!$this->exists($prev)) {
            throw new \RuntimeException(sprintf('Проект не зарегистрирован: %s', $prev));
        }

        if ($this->exists($next)) {
            throw new \RuntimeException(sprintf('Кодовое имя проекта уже занято: %s', $next));
        }

        $config = $this->load($prev);
        $data = $config['data'] ?? null;

        if (is_array($data)) {
            $project = $data['project'] ?? null;

            if (is_array($project)) {
                $project['name'] = $next;
                $data['project'] = $project;
                $config['data'] = $data;
            }
        }

        $prevDir = dirname($this->getConfigFile($prev));
        $nextDir = dirname($this->getConfigFile($next));

        if (!rename($prevDir, $nextDir)) {
            throw new \RuntimeException(sprintf('Не удалось переименовать проект %s в %s.', $prev, $next));
        }

        $this->save($next, $config);
    }

    public function isValidCodename(string $codename): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9._-]*$/', $codename);
    }

    public function delete(string $codename): void
    {
        $dir = dirname($this->getConfigFile($codename));

        if (!is_dir($dir)) {
            throw new \RuntimeException(sprintf('Проект не зарегистрирован: %s', $codename));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
