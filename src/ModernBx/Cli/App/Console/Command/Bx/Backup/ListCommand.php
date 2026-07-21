<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\BxCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function ModernBx\CommonFunctions\to_json;

final class ListCommand extends BxCommand
{
    /** @var string */
    protected static $defaultName = 'backup:list';

    protected function configure(): void
    {
        $this
            ->setDescription('Выводит список основных файлов резервных копий Bitrix')
            ->setHelp(
                'Сканирует /bitrix/backup, выводит основные .gz-файлы без номера тома и проверяет, '
                . 'что дополнительные тома идут по порядку без пропусков.',
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        $this->printer->info($this->encodeItems($this->executeLocal()));
    }

    /** @return list<array<string, mixed>> */
    protected function executeLocal(): array
    {
        $documentRoot = rtrim($this->getDocumentRoot()->toString(), '/');
        $backupDirectory = $documentRoot . '/bitrix/backup';

        if (!is_dir($backupDirectory)) {
            throw new \RuntimeException(
                'Директория резервных копий не найдена: /bitrix/backup',
                static::CODE_IO_ERROR,
            );
        }

        if (!is_readable($backupDirectory)) {
            throw new \RuntimeException(
                'Директория резервных копий недоступна для чтения: /bitrix/backup',
                static::CODE_IO_ERROR,
            );
        }

        $entries = scandir($backupDirectory);

        if ($entries === false) {
            throw new \RuntimeException(
                'Не удалось прочитать директорию резервных копий: /bitrix/backup',
                static::CODE_IO_ERROR,
            );
        }

        $names = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
        ));
        sort($names, SORT_STRING);

        $items = [];

        foreach ($names as $name) {
            if (!$this->isMainBackupName($name) || !is_file($backupDirectory . '/' . $name)) {
                continue;
            }

            $volumes = $this->findVolumes($name, $names, $backupDirectory);
            $this->assertVolumesSequential($name, $volumes);
            $items[] = $this->buildItem($documentRoot, $backupDirectory . '/' . $name, $volumes);
        }

        return $items;
    }

    /** @param list<int> $volumes */
    protected function assertVolumesSequential(string $name, array $volumes): void
    {
        foreach ($volumes as $index => $volume) {
            $expected = $index + 1;

            if ($volume !== $expected) {
                throw new \RuntimeException(
                    sprintf(
                        'Тома резервной копии %s идут с пропуском: ожидается .%d, найден .%d.',
                        $name,
                        $expected,
                        $volume,
                    ),
                    static::CODE_IO_ERROR,
                );
            }
        }
    }

    /**
     * @param list<int> $volumes
     * @return array<string, mixed>
     */
    protected function buildItem(string $documentRoot, string $path, array $volumes): array
    {
        $stat = stat($path);

        if ($stat === false) {
            throw new \RuntimeException(
                sprintf('Не удалось прочитать информацию о файле: %s', basename($path)),
            );
        }

        return [
            'name' => basename($path),
            'path' => '/' . ltrim(substr($path, strlen($documentRoot)), '/'),
            'size' => (int) $stat['size'],
            'mtime' => (int) $stat['mtime'],
            'volumes' => $volumes,
        ];
    }

    protected function isMainBackupName(string $name): bool
    {
        return preg_match('/\.gz$/', $name) === 1;
    }

    /**
     * @param list<string> $names
     * @return list<int>
     */
    protected function findVolumes(string $mainName, array $names, string $backupDirectory): array
    {
        $volumes = [];
        $pattern = '/^' . preg_quote($mainName, '/') . '\\.(\\d+)$/';

        foreach ($names as $name) {
            $matchesPattern = preg_match($pattern, $name, $matches) === 1;

            if (!$matchesPattern || !is_file($backupDirectory . '/' . $name)) {
                continue;
            }

            $number = (int) $matches[1];

            if ($number > 0) {
                $volumes[] = $number;
            }
        }

        sort($volumes, SORT_NUMERIC);

        return $volumes;
    }

    /** @param list<array<string, mixed>> $items */
    protected function encodeItems(array $items): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return (string) to_json($items, $flags);
    }
}
