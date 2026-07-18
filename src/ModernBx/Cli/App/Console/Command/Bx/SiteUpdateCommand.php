<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx;

use ModernBx\Cli\App\Console\Mixin\Common\IO;
use ModernBx\Cli\App\Validation\JsonSchemaValidator;
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
                        'fields',
                        InputArgument::REQUIRED,
                        $this->trans("argument.site.update_fields"),
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
        $fields = $input->getArgument("fields");

        if (!is_string($lid)) {
            throw new \Exception($this->trans("error.site.lid_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        if (!is_string($fields)) {
            throw new \Exception($this->trans("error.site.update_json_string"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        /** @var array<string, mixed> $decodedFields */
        $decodedFields = $this->decodeFields($fields);
        $this->validateFields($decodedFields);

        /** @noinspection PhpUndefinedClassInspection */
        /** @phpstan-ignore-next-line */
        $result = \Bitrix\Main\SiteTable::update($lid, $decodedFields);

        if (!$result->isSuccess()) {
            throw new \Exception(implode(PHP_EOL, $result->getErrorMessages()), static::CODE_INVALID_ARGUMENT_VALUE);
        }
    }

    /**
     * @param string $fields
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function decodeFields(string $fields): array
    {
        $decoded = json_decode($fields, true);

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

    /**
     * @param array<string, mixed> $fields
     * @throws \Exception
     */
    private function validateFields(array $fields): void
    {
        $schemaPath = $this->getFieldsSchemaPath();
        $schema = $this->loadFieldsSchema($schemaPath);

        try {
            $errors = (new JsonSchemaValidator())->validate($fields, $schema, $schemaPath);
        } catch (\InvalidArgumentException $exception) {
            throw new \Exception(
                $this->trans("error.site.update_schema_invalid", ["%message%" => $exception->getMessage()]),
                static::CODE_INVALID_ARGUMENT_VALUE,
                $exception
            );
        }

        if ($errors !== []) {
            throw new \Exception(
                $this->trans("error.site.update_schema", ["%message%" => implode(PHP_EOL, $errors)]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }
    }

    /**
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function loadFieldsSchema(string $schemaPath): array
    {
        $schemaJson = file_get_contents($schemaPath);

        if ($schemaJson === false) {
            throw new \Exception(
                $this->trans("error.site.update_schema_read", ["%file%" => $schemaPath]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $schema = json_decode($schemaJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($schema) || array_is_list($schema)) {
            throw new \Exception(
                $this->trans("error.site.update_schema_invalid", ["%message%" => json_last_error_msg()]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        /** @var array<string, mixed> $schema */
        return $schema;
    }

    private function getFieldsSchemaPath(): string
    {
        return __DIR__ . '/Validation/SiteUpdateFields.schema.json';
    }
}
