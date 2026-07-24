<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Vendor;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class GetInstallerCommand extends Command
{
    protected static $defaultName = 'vendor:get-installer';

    private const BASE_URL = 'https://www.1c-bitrix.ru/download/';
    private const CACHE_DIRECTORY = '.config/bx-cli/cache/vendor-restore/distro';

    /** @var array<string, array<string, string>> */
    private const EDITIONS = [
        'bitrix' => [
            'business' => 'business_encode_php5.tar.gz',
            'expert' => 'expert_encode_php5.tar.gz',
            'small_business' => 'small_business_encode_php5.tar.gz',
            'standard' => 'standard_encode_php5.tar.gz',
            'start' => 'start_encode_php5.tar.gz',
        ],
        'bitrix24' => [
            'business' => 'portal/bitrix24_encode_php5.tar.gz',
            'enterprise' => 'portal/bitrix24_enterprise_encode_php5.tar.gz',
            'enterprise_postgresql' => 'portal/bitrix24_enterprise_postgresql_encode.zip',
        ],
    ];

    public function __construct()
    {
        parent::__construct('vendor:get-installer');
        $this->setDescription('Скачать дистрибутив 1С-Битрикс или 1С-Битрикс24.');
        $this->addOption('product', null, InputOption::VALUE_REQUIRED, 'Продукт: bitrix или bitrix24.', 'bitrix');
        $this->addOption('edition', null, InputOption::VALUE_REQUIRED, 'Редакция продукта.', 'start');
        $this->addOption(
            'path',
            null,
            InputOption::VALUE_REQUIRED,
            'Файл или директория для сохранения архива.',
            getcwd() ?: '.'
        );
        $this->addOption('extract', null, InputOption::VALUE_NONE, 'Распаковать архив рядом с ним и удалить архив.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $product = $this->stringOption($input, 'product');
        $edition = $this->stringOption($input, 'edition');
        $path = $this->stringOption($input, 'path');

        if (!isset(self::EDITIONS[$product])) {
            $output->writeln(sprintf(
                '<error>Неизвестный продукт "%s". Доступно: %s.</error>',
                $product,
                implode(', ', array_keys(self::EDITIONS))
            ));
            return 2;
        }

        if (!isset(self::EDITIONS[$product][$edition])) {
            $output->writeln(sprintf(
                '<error>Неизвестная редакция "%s" для продукта "%s". Доступно: %s.</error>',
                $edition,
                $product,
                implode(', ', array_keys(self::EDITIONS[$product]))
            ));
            return 2;
        }

        $remotePath = self::EDITIONS[$product][$edition];
        $url = self::BASE_URL . $remotePath;
        $target = $this->resolveTargetPath($path, basename($remotePath));

        if (file_exists($target)) {
            $output->writeln(sprintf('<error>Файл уже существует: %s</error>', $target));
            return 1;
        }

        $directory = dirname($target);
        if (!is_dir($directory)) {
            $output->writeln(sprintf('<error>Директория не найдена: %s</error>', $directory));
            return 1;
        }

        $cache = $this->cachePath(basename($remotePath));
        $contentLength = $this->contentLength($url);

        if ($contentLength !== null && $this->cacheIsFresh($cache, $contentLength)) {
            $output->writeln(sprintf('<info>Использую кешированный архив: %s</info>', $cache));
            $this->copyCachedFile($cache, $target);
        } else {
            $output->writeln(sprintf('<info>Скачиваю %s/%s: %s</info>', $product, $edition, $url));
            $this->download($url, $cache);
            $this->copyCachedFile($cache, $target);
        }

        $output->writeln(sprintf('<info>Архив сохранен: %s</info>', $target));

        if ((bool) $input->getOption('extract')) {
            $this->extract($target, $directory);
            unlink($target);
            $output->writeln(sprintf('<info>Архив распакован в %s и удален.</info>', $directory));
        }

        return 0;
    }

    private function stringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        return is_string($value) && $value !== '' ? $value : '';
    }

    private function resolveTargetPath(string $path, string $remoteFilename): string
    {
        if ($path === '' || is_dir($path) || str_ends_with($path, DIRECTORY_SEPARATOR)) {
            return rtrim($path === '' ? '.' : $path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $remoteFilename;
        }

        return $path;
    }

    private function cachePath(string $remoteFilename): string
    {
        $home = getenv('HOME');
        if (!is_string($home) || $home === '') {
            throw new \RuntimeException('Не удалось определить домашнюю директорию для кеша.');
        }

        return implode(DIRECTORY_SEPARATOR, [
            rtrim($home, DIRECTORY_SEPARATOR),
            self::CACHE_DIRECTORY,
            $remoteFilename,
        ]);
    }

    private function cacheIsFresh(string $cache, int $contentLength): bool
    {
        return is_file($cache) && filesize($cache) === $contentLength;
    }

    private function copyCachedFile(string $cache, string $target): void
    {
        $read = @fopen($cache, 'rb');
        if (!is_resource($read)) {
            throw new \RuntimeException(sprintf('Не удалось открыть кешированный файл: %s', $cache));
        }

        $write = @fopen($target, 'xb');
        if (!is_resource($write)) {
            fclose($read);
            throw new \RuntimeException(sprintf('Не удалось создать файл: %s', $target));
        }

        try {
            if (stream_copy_to_stream($read, $write) === false) {
                throw new \RuntimeException(sprintf('Не удалось скопировать кешированный файл: %s', $cache));
            }
        } finally {
            fclose($read);
            fclose($write);
        }
    }

    private function contentLength(string $url): ?int
    {
        $headers = @get_headers($url, true, stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'user_agent' => 'BitrixSiteLoader',
            ],
        ]));

        if (!is_array($headers)) {
            return null;
        }

        $statusLine = $headers[0] ?? '';
        if (is_string($statusLine)
            && preg_match('/^HTTP\/\S+\s+(\d+)/', $statusLine, $matches) === 1
            && (int) $matches[1] >= 400
        ) {
            throw new \RuntimeException(sprintf('Сервер вернул ошибку %s для %s', $matches[1], $url));
        }

        $value = $headers['Content-Length'] ?? $headers['content-length'] ?? null;
        if (is_array($value)) {
            $value = end($value);
        }

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    private function download(string $url, string $target): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Не удалось создать директорию кеша: %s', $directory));
        }

        $temporaryTarget = $target . '.tmp.' . bin2hex(random_bytes(6));

        $read = @fopen($url, 'rb', false, stream_context_create(['http' => ['user_agent' => 'BitrixSiteLoader']]));
        if (!is_resource($read)) {
            throw new \RuntimeException(sprintf('Не удалось открыть URL: %s', $url));
        }

        $write = @fopen($temporaryTarget, 'xb');
        if (!is_resource($write)) {
            fclose($read);
            throw new \RuntimeException(sprintf('Не удалось создать файл: %s', $temporaryTarget));
        }

        try {
            if (stream_copy_to_stream($read, $write) === false) {
                throw new \RuntimeException(sprintf('Не удалось скачать файл: %s', $url));
            }
        } finally {
            fclose($read);
            fclose($write);
        }

        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $statusLine, $matches) === 1
            && (int) $matches[1] >= 400
        ) {
            @unlink($temporaryTarget);
            throw new \RuntimeException(sprintf('Сервер вернул ошибку %s для %s', $matches[1], $url));
        }

        if (!@rename($temporaryTarget, $target)) {
            @unlink($temporaryTarget);
            throw new \RuntimeException(sprintf('Не удалось обновить кешированный файл: %s', $target));
        }
    }

    /** @param list<string> $command */
    private function runProcess(array $command): void
    {
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException(sprintf('Не удалось запустить команду: %s', implode(' ', $command)));
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf(
                'Команда завершилась с кодом %d: %s',
                $exitCode,
                implode(' ', $command)
            ));
        }
    }

    private function extract(string $archive, string $directory): void
    {
        if (str_ends_with($archive, '.zip')) {
            $zip = new \ZipArchive();
            if ($zip->open($archive) !== true) {
                throw new \RuntimeException(sprintf('Не удалось открыть zip-архив: %s', $archive));
            }
            $zip->extractTo($directory);
            $zip->close();
            return;
        }

        if (str_ends_with($archive, '.tar.gz')) {
            $this->runProcess(['tar', '-xzf', $archive, '-C', $directory]);
            return;
        }

        throw new \RuntimeException(sprintf('Неизвестный формат архива: %s', $archive));
    }
}
