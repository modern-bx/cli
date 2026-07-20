<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Site;

use ModernBx\Cli\App\Validation\JsonSchemaValidator;

trait SiteFieldsValidationTrait
{
    /**
     * @param string $fields
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function decodeFields(string $fields): array
    {
        $decoded = json_decode($fields);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                $this->trans("error.site.update_invalid_json", ["%message%" => json_last_error_msg()]),
                static::CODE_INVALID_ARGUMENT_VALUE
            );
        }

        if (!$decoded instanceof \stdClass) {
            throw new \Exception($this->trans("error.site.update_object"), static::CODE_INVALID_ARGUMENT_VALUE);
        }

        return $this->jsonObjectToArray($decoded);
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $fields
     * @throws \Exception
     */
    private function validateFields(array $fields): void
    {
        $schemaPath = $this->getFieldsSchemaPath();
        $schema = $this->loadFieldsSchema($schemaPath);

        try {
            $errors = (new JsonSchemaValidator(
                fn (string $key, array $parameters = []): string => $this->trans($key, $parameters)
            ))->validate($fields, $schema, $schemaPath);
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
        return dirname(__DIR__, 2) . '/Validation/SiteUpdateFields.schema.json';
    }
}
