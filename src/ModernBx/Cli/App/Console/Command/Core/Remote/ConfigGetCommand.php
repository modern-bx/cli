<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Core\Remote;

use ModernBx\Cli\App\Console\Command\AppCommand;
use ModernBx\Cli\App\Service\Remote\BitrixAdminClient;
use ModernBx\Cli\App\Service\Remote\RemoteConfigPhpCodeBuilder;
use ModernBx\Cli\App\Service\Remote\RemoteConfigParameters;
use ModernBx\Cli\App\Service\Remote\RemotePhpTrait;
use ModernBx\Cli\App\Service\Remote\RemoteProjectConfigManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigGetCommand extends AppCommand
{
    use RemotePhpTrait;

    protected static $defaultName = 'remote:config-get';

    private RemoteConfigPhpCodeBuilder $remoteConfigPhpCodeBuilder;

    public function __construct(
        RemoteProjectConfigManager $remoteProjectConfigManager,
        BitrixAdminClient $bitrixAdminClient,
        RemoteConfigPhpCodeBuilder $remoteConfigPhpCodeBuilder
    ) {
        parent::__construct();
        $this->remoteProjectConfigManager = $remoteProjectConfigManager;
        $this->bitrixAdminClient = $bitrixAdminClient;
        $this->remoteConfigPhpCodeBuilder = $remoteConfigPhpCodeBuilder;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Выводит важные параметры конфигурации текущего remote')
            ->addArgument(
                'parameter',
                InputArgument::REQUIRED,
                'Параметр или список через запятую: ' . implode(', ', RemoteConfigParameters::ALL),
            );
    }

    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        $remote = $this->getSessionRemote();
        if ($remote === null) {
            throw new \RuntimeException('Remote текущей терминальной сессии не указан.');
        }

        $parameters = $this->parseParameters($input->getArgument('parameter'));
        $result = $this->decodeRemoteJsonResult(
            $this->executeRemotePhp($remote, $this->remoteConfigPhpCodeBuilder->build($parameters)),
            'Не удалось получить конфигурацию удаленного проекта.',
        );

        if (!is_array($result) || count($result) !== count($parameters)) {
            throw new \RuntimeException('Удаленный проект вернул некорректный список параметров.');
        }

        foreach ($result as $value) {
            if (!is_scalar($value)) {
                throw new \RuntimeException('Удаленный проект вернул некорректное значение параметра.');
            }
            $this->printer->info((string) $value);
        }
    }

    /** @return string[] */
    private function parseParameters(mixed $argument): array
    {
        if (!is_string($argument)) {
            throw new \RuntimeException('Аргумент parameter должен быть строкой.', static::CODE_INVALID_ARGUMENT_VALUE);
        }

        $parameters = array_map('trim', explode(',', $argument));
        if (in_array('', $parameters, true)) {
            throw new \RuntimeException(
                'Список параметров не должен быть пустым.',
                static::CODE_INVALID_ARGUMENT_VALUE,
            );
        }

        foreach ($parameters as $parameter) {
            if (!in_array($parameter, RemoteConfigParameters::ALL, true)) {
                $available = implode(', ', RemoteConfigParameters::ALL);
                throw new \RuntimeException(
                    'Неизвестный параметр: ' . $parameter . '. Доступно: ' . $available,
                    static::CODE_INVALID_ARGUMENT_VALUE,
                );
            }
        }

        return $parameters;
    }
}
