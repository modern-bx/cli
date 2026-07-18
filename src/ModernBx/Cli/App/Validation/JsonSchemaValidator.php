<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Validation;

/**
 * Lightweight validator for the JSON Schema keywords used by command argument schemas.
 *
 * The schema files stay in the standard JSON Schema format, so they can be read by humans
 * and by full-featured validators if deeper validation rules are needed later.
 */
final class JsonSchemaValidator
{
    /**
     * @param mixed $value
     * @param array<string, mixed> $schema
     * @return list<string>
     */
    public function validate(mixed $value, array $schema, ?string $schemaPath = null): array
    {
        $schema = $this->includeSchemas(
            $schema,
            $schemaPath === null ? null : dirname($schemaPath),
            $schemaPath === null ? [] : [$schemaPath]
        );

        return $this->validateAgainstSchema($value, $schema, $schema, '$');
    }


    /**
     * @param array<string, mixed> $schema
     * @param list<string> $includeStack
     * @return array<string, mixed>
     */
    private function includeSchemas(array $schema, ?string $baseDirectory, array $includeStack): array
    {
        if (!isset($schema['$include'])) {
            return $schema;
        }

        if (!is_array($schema['$include'])) {
            throw new \InvalidArgumentException('JSON Schema $include must be an array of strings.');
        }

        $mergedSchema = $schema;
        unset($mergedSchema['$include']);

        foreach ($schema['$include'] as $includePath) {
            if (!is_string($includePath)) {
                throw new \InvalidArgumentException('JSON Schema $include must contain only strings.');
            }

            $includedSchemaPath = $this->resolveIncludePath($includePath, $baseDirectory);
            if (in_array($includedSchemaPath, $includeStack, true)) {
                throw new \InvalidArgumentException(
                    sprintf('Circular JSON Schema include detected: %s', $includedSchemaPath)
                );
            }

            $includedSchemaJson = file_get_contents($includedSchemaPath);
            if ($includedSchemaJson === false) {
                throw new \InvalidArgumentException(
                    sprintf('Unable to read included JSON Schema: %s', $includedSchemaPath)
                );
            }

            $includedSchema = json_decode($includedSchemaJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($includedSchema) || array_is_list($includedSchema)) {
                throw new \InvalidArgumentException(
                    sprintf('Included JSON Schema is invalid: %s', $includedSchemaPath)
                );
            }

            /** @var array<string, mixed> $includedSchema */
            $includedSchema = $this->includeSchemas(
                $includedSchema,
                dirname($includedSchemaPath),
                array_merge($includeStack, [$includedSchemaPath])
            );
            $mergedSchema = $this->mergeIncludedSchema($mergedSchema, $includedSchema, $includedSchemaPath);
        }

        return $mergedSchema;
    }

    private function resolveIncludePath(string $includePath, ?string $baseDirectory): string
    {
        if ($includePath === '') {
            throw new \InvalidArgumentException('JSON Schema $include path must not be empty.');
        }

        $resolvedPath = str_starts_with($includePath, '/')
            ? $includePath
            : ($baseDirectory === null ? $includePath : $baseDirectory . '/' . $includePath);
        $realPath = realpath($resolvedPath);

        if ($realPath === false) {
            throw new \InvalidArgumentException(sprintf('Included JSON Schema does not exist: %s', $resolvedPath));
        }

        return $realPath;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $includedSchema
     * @return array<string, mixed>
     */
    private function mergeIncludedSchema(array $schema, array $includedSchema, string $includedSchemaPath): array
    {
        if (!isset($includedSchema['$defs'])) {
            return $schema;
        }

        if (!is_array($includedSchema['$defs']) || array_is_list($includedSchema['$defs'])) {
            throw new \InvalidArgumentException(
                sprintf('Included JSON Schema $defs must be an object: %s', $includedSchemaPath)
            );
        }

        if (!isset($schema['$defs'])) {
            $schema['$defs'] = [];
        }

        if (!is_array($schema['$defs']) || array_is_list($schema['$defs'])) {
            throw new \InvalidArgumentException('JSON Schema $defs must be an object.');
        }

        foreach ($includedSchema['$defs'] as $name => $definition) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException(
                    sprintf('Included JSON Schema $defs contains an invalid key: %s', $includedSchemaPath)
                );
            }

            if (isset($schema['$defs'][$name])) {
                throw new \InvalidArgumentException(
                    sprintf('Duplicate JSON Schema definition "%s" included from %s.', $name, $includedSchemaPath)
                );
            }

            $schema['$defs'][$name] = $definition;
        }

        return $schema;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $rootSchema
     * @return list<string>
     */
    private function validateAgainstSchema(mixed $value, array $schema, array $rootSchema, string $path): array
    {
        if (isset($schema['$ref'])) {
            if (!is_string($schema['$ref'])) {
                return [sprintf('%s has an invalid $ref.', $path)];
            }

            return $this->validateAgainstSchema(
                $value,
                $this->resolveRef($schema['$ref'], $rootSchema),
                $rootSchema,
                $path
            );
        }

        if (isset($schema['anyOf']) && is_array($schema['anyOf'])) {
            foreach ($schema['anyOf'] as $subSchema) {
                if (is_array($subSchema)
                    && $this->validateAgainstSchema($value, $subSchema, $rootSchema, $path) === []
                ) {
                    return [];
                }
            }

            return [sprintf('%s does not match any allowed schema.', $path)];
        }

        $errors = [];
        if (isset($schema['type'])) {
            $types = is_array($schema['type']) ? $schema['type'] : [$schema['type']];
            if (!$this->matchesAnyType($value, $types)) {
                $errors[] = sprintf('%s must be of type %s.', $path, implode('|', $types));
                return $errors;
            }
        }

        if (array_key_exists('enum', $schema)
            && is_array($schema['enum'])
            && !in_array($value, $schema['enum'], true)
        ) {
            $errors[] = sprintf('%s must be one of the allowed values.', $path);
        }

        if (is_array($value) && !array_is_list($value)) {
            $errors = array_merge($errors, $this->validateObject($value, $schema, $rootSchema, $path));
        }

        if (is_array($value) && array_is_list($value) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($value as $index => $item) {
                $errors = array_merge(
                    $errors,
                    $this->validateAgainstSchema($item, $schema['items'], $rootSchema, $path . '[' . $index . ']')
                );
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $rootSchema
     * @return list<string>
     */
    private function validateObject(array $value, array $schema, array $rootSchema, string $path): array
    {
        $errors = [];
        $properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : [];

        if (isset($schema['minProperties'])
            && is_int($schema['minProperties'])
            && count($value) < $schema['minProperties']
        ) {
            $errors[] = sprintf('%s must contain at least %d properties.', $path, $schema['minProperties']);
        }

        foreach ($value as $property => $propertyValue) {
            if (!is_string($property)) {
                $errors[] = sprintf('%s contains an invalid property name.', $path);
                continue;
            }

            if (isset($properties[$property]) && is_array($properties[$property])) {
                $errors = array_merge(
                    $errors,
                    $this->validateAgainstSchema(
                        $propertyValue,
                        $properties[$property],
                        $rootSchema,
                        $path . '.' . $property
                    )
                );
                continue;
            }

            if (($schema['additionalProperties'] ?? true) === false) {
                $errors[] = sprintf('%s.%s is not an allowed property.', $path, $property);
                continue;
            }

            if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
                $errors = array_merge(
                    $errors,
                    $this->validateAgainstSchema(
                        $propertyValue,
                        $schema['additionalProperties'],
                        $rootSchema,
                        $path . '.' . $property
                    )
                );
            }
        }

        return $errors;
    }

    /**
     * @param list<mixed> $types
     */
    private function matchesAnyType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            if ($type === 'object' && is_array($value) && !array_is_list($value)) {
                return true;
            }
            if ($type === 'array' && is_array($value) && array_is_list($value)) {
                return true;
            }
            if ($type === 'string' && is_string($value)) {
                return true;
            }
            if ($type === 'integer' && is_int($value)) {
                return true;
            }
            if ($type === 'number' && (is_int($value) || is_float($value))) {
                return true;
            }
            if ($type === 'boolean' && is_bool($value)) {
                return true;
            }
            if ($type === 'null' && $value === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $rootSchema
     * @return array<string, mixed>
     */
    private function resolveRef(string $ref, array $rootSchema): array
    {
        if (!str_starts_with($ref, '#/')) {
            throw new \InvalidArgumentException(sprintf('Only local JSON Schema references are supported: %s', $ref));
        }

        $current = $rootSchema;
        foreach (explode('/', substr($ref, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (!is_array($current) || !isset($current[$segment])) {
                throw new \InvalidArgumentException(sprintf('Unable to resolve JSON Schema reference: %s', $ref));
            }
            $current = $current[$segment];
        }

        if (!is_array($current)) {
            throw new \InvalidArgumentException(sprintf('JSON Schema reference does not point to an object: %s', $ref));
        }

        return $current;
    }
}
