<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\Common\IO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SiteUpdateCommand extends KernelCommand
{
    use IO;

    /**
     * @var string
     */
    protected static $defaultName = 'site:update';

    protected function configure(): void
    {
        $this
            ->setDescription($this->trans("command.site_update.description"))
            ->setHelp($this->trans("command.site_update.help"))
            ->setDefinition(
                new InputDefinition([
                    new InputArgument(
                        'LID',
                        InputArgument::REQUIRED,
                        $this->trans("argument.site.lid"),
                    ),
                    new InputArgument(
                        'values',
                        InputArgument::REQUIRED,
                        $this->trans("argument.site.update_values"),
                    ),
                ]),
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function executeInternal(InputInterface $input, OutputInterface $output): void
    {
        parent::executeInternal($input, $output);

        $lid = $input->getArgument("LID");
        $values = $input->getArgument("values");

        if (!is_string($lid)) {
            throw new \Exception($this->trans("error.site.lid_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if (!is_string($values)) {
            throw new \Exception($this->trans("error.site.update_json_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        /** @var array<string, mixed> $decodedValues */
        $decodedValues = $this->decodeValues($values);

        /** @noinspection PhpUndefinedClassInspection */
        /** @phpstan-ignore-next-line */
        $result = \Bitrix\Main\SiteTable::update($lid, $decodedValues);

        if (!$result->isSuccess()) {
            throw new \Exception(implode(PHP_EOL, $result->getErrorMessages()), static::CODE_INVALID_ARGUMENT_VALUE);
        }
    }

    /**
     * @param string $values
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function decodeValues(string $values): array
    {
        $decoded = json_decode($values, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                $this->trans("error.site.update_invalid_json", ["%message%" => json_last_error_msg()]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \Exception($this->trans("error.site.update_object"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
