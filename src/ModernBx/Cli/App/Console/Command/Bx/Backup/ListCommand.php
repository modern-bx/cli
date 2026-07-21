<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Backup;

use ModernBx\Cli\App\Console\Command\BxCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteBackupPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ListCommand extends BxCommand
{
    use RemotePhpTrait;

    /** @var string */
    protected static $defaultName = 'backup:list';

    protected RemoteBackupPhpCodeBuilder $remoteBackupPhpCodeBuilder;

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteBackupPhpCodeBuilder $remoteBackupPhpCodeBuilder
    ) {
        parent::__construct();

        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteBackupPhpCodeBuilder = $remoteBackupPhpCodeBuilder;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Выводит список основных файлов резервных копий Bitrix')
            ->setHelp(
                'Сканирует /bitrix/backup, выводит основные .gz-файлы без номера тома и проверяет, '
                . 'что дополнительные тома идут по порядку без пропусков.',
            )
            ->addOption('remote', null, InputOption::VALUE_REQUIRED, 'Кодовое имя удаленного проекта')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Отключить неявный remote текущей сессии')
            ->addOption(
                'list-all',
                null,
                InputOption::VALUE_NONE,
                'Вывести все основные файлы, включая резервные копии с пропущенными томами',
            )
            ->addOption(
                'list-incomplete',
                null,
                InputOption::VALUE_NONE,
                'Вывести только резервные копии с пропущенными томами',
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $input->getOption('remote');
        $listAll = $input->getOption('list-all') === true;
        $listIncomplete = $input->getOption('list-incomplete') === true;

        if ($listAll && $listIncomplete) {
            throw new \RuntimeException(
                'Опции --list-all и --list-incomplete нельзя указывать одновременно.',
                static::CODE_INVALID_OPTION_VALUE,
            );
        }

        if (is_string($remote)) {
            $this->printer = $this->getPrinter($output);
            $this->verbose = $input->getOption('verbose') !== false;
            $items = $this->executeRemote($remote);
        } else {
            parent::executeInternal($input, $output);
            $items = $this->executeLocal();
        }

        $this->printItems($this->filterItems($items, $listAll, $listIncomplete));
    }


    /** @return list<array<string, mixed>> */
    protected function executeRemote(string $codename): array
    {
        $result = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($codename, $this->remoteBackupPhpCodeBuilder->buildList()),
            'Не удалось получить список резервных копий удаленного проекта.',
        );

        if (!is_array($result)) {
            throw new \RuntimeException('Удаленная PHP-консоль вернула некорректный список резервных копий.');
        }

        return array_values(array_filter($result, 'is_array'));
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
            $items[] = $this->buildItem(
                $documentRoot,
                $backupDirectory . '/' . $name,
                $volumes,
                $this->findFirstMissingVolume($volumes),
            );
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    protected function filterItems(array $items, bool $listAll, bool $listIncomplete): array
    {
        return array_values(array_filter(
            $items,
            static function (array $item) use ($listAll, $listIncomplete): bool {
                $incomplete = ($item['incomplete'] ?? false) === true;

                if ($listIncomplete) {
                    return $incomplete;
                }

                if ($listAll) {
                    return true;
                }

                return !$incomplete;
            },
        ));
    }

    /** @param list<array<string, mixed>> $items */
    protected function printItems(array $items): void
    {
        foreach ($items as $item) {
            $line = $this->formatItem($item);

            if (($item['incomplete'] ?? false) === true) {
                $this->printer->error($line);
                continue;
            }

            $this->printer->info($line);
        }
    }

    /** @param array<string, mixed> $item */
    protected function formatItem(array $item): string
    {
        $path = $item['path'] ?? '';

        if (!is_scalar($path)) {
            return '';
        }

        return (string) $path;
    }

    /**
     * @param list<int> $volumes
     * @return array<string, mixed>
     */
    protected function buildItem(string $documentRoot, string $path, array $volumes, ?int $missingVolume): array
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
            'incomplete' => $missingVolume !== null,
            'missing_volume' => $missingVolume,
        ];
    }

    protected function isMainBackupName(string $name): bool
    {
        return preg_match('/\.gz$/', $name) === 1;
    }

    /** @param list<int> $volumes */
    protected function findFirstMissingVolume(array $volumes): ?int
    {
        foreach ($volumes as $index => $volume) {
            $expected = $index + 1;

            if ($volume !== $expected) {
                return $expected;
            }
        }

        return null;
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
}
