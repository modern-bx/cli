<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\IBlock\Element;

use ModernBx\Cli\App\Validation\JsonSchemaValidator;

trait ElementFieldsValidationTrait
{
    private function readFieldsArgumentOrStdin(mixed $fields): string
    {
        if (is_string($fields)) {
            return $fields;
        }

        if ($fields !== null) {
            throw new \Exception(
                $this->trans('error.iblock_element.update_json_string'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $stdinIsTty = function_exists('posix_isatty') && posix_isatty(STDIN);
        if ($stdinIsTty) {
            throw new \Exception(
                $this->trans('error.iblock_element.fields_required'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $stdin = stream_get_contents(STDIN);
        if (!is_string($stdin) || trim($stdin) === '') {
            throw new \Exception(
                $this->trans('error.iblock_element.fields_required'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        return $stdin;
    }

    /** @return array<string, mixed> */
    private function decodeFields(string $fields): array
    {
        $decoded = json_decode($fields);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                $this->trans('error.iblock_element.update_invalid_json', ['%message%' => json_last_error_msg()]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        if (!$decoded instanceof \stdClass) {
            throw new \Exception(
                $this->trans('error.iblock_element.update_object'),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        return $this->jsonObjectToArray($decoded);
    }

    /** @return array<string, mixed> */
    private function jsonObjectToArray(\stdClass $object): array
    {
        $result = [];

        foreach (get_object_vars($object) as $key => $value) {
            $result[$key] = $this->jsonValueToArray($value);
        }

        return $result;
    }

    private function jsonValueToArray(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            return $this->jsonObjectToArray($value);
        }

        if (is_array($value)) {
            return array_map([$this, 'jsonValueToArray'], $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $fields */
    private function validateFields(array $fields): void
    {
        $schemaPath = dirname(__DIR__, 3) . '/Validation/IBlockElementUpdateFields.schema.json';
        $schemaJson = file_get_contents($schemaPath);

        if ($schemaJson === false) {
            throw new \Exception(
                $this->trans('error.iblock_element.update_schema_read', ['%file%' => $schemaPath]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        $schema = json_decode($schemaJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($schema) || array_is_list($schema)) {
            throw new \Exception(
                $this->trans('error.iblock_element.update_schema_invalid', ['%message%' => json_last_error_msg()]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        try {
            $errors = (new JsonSchemaValidator(
                fn (string $key, array $parameters = []): string => $this->trans($key, $parameters)
            ))->validate($fields, $schema, $schemaPath);
        } catch (\InvalidArgumentException $exception) {
            throw new \Exception(
                $this->trans('error.iblock_element.update_schema_invalid', ['%message%' => $exception->getMessage()]),
                static::CODE_INVALID_ARGUMENT_VALUE,
                $exception
            );
        }

        if ($errors !== []) {
            throw new \Exception(
                $this->trans('error.iblock_element.update_schema', ['%message%' => implode(PHP_EOL, $errors)]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }
    }
}
