<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Validation;

/**
 * Lightweight validator for the JSON Schema keywords used by command argument schemas.
 *
 * The schema files stay close to JSON Schema while adding project-specific $mixin
 * and $include keys for reusable definitions and open object fields.
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
        $schema = $this->mixinSchemas(
            $schema,
            $schemaPath === null ? null : dirname($schemaPath),
            $schemaPath === null ? [] : [$schemaPath]
        );

        return $this->validateAgainstSchema($value, $schema, $schema, '$');
    }


    /**
     * @param array<string, mixed> $schema
     * @param list<string> $mixinStack
     * @return array<string, mixed>
     */
    private function mixinSchemas(array $schema, ?string $baseDirectory, array $mixinStack): array
    {
        if (!isset($schema['$mixin'])) {
            return $schema;
        }

        if (!is_array($schema['$mixin'])) {
            throw new \InvalidArgumentException('JSON Schema $mixin must be an array of strings.');
        }

        $mergedSchema = $schema;
        unset($mergedSchema['$mixin']);

        foreach ($schema['$mixin'] as $mixinPath) {
            if (!is_string($mixinPath)) {
                throw new \InvalidArgumentException('JSON Schema $mixin must contain only strings.');
            }

            $mixedSchemaPath = $this->resolveMixinPath($mixinPath, $baseDirectory);
            if (in_array($mixedSchemaPath, $mixinStack, true)) {
                throw new \InvalidArgumentException(
                    sprintf('Circular JSON Schema mixin detected: %s', $mixedSchemaPath)
                );
            }

            $mixedSchemaJson = file_get_contents($mixedSchemaPath);
            if ($mixedSchemaJson === false) {
                throw new \InvalidArgumentException(
                    sprintf('Unable to read mixed JSON Schema: %s', $mixedSchemaPath)
                );
            }

            $mixedSchema = json_decode($mixedSchemaJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($mixedSchema) || array_is_list($mixedSchema)) {
                throw new \InvalidArgumentException(
                    sprintf('Mixed JSON Schema is invalid: %s', $mixedSchemaPath)
                );
            }

            /** @var array<string, mixed> $mixedSchema */
            $mixedSchema = $this->mixinSchemas(
                $mixedSchema,
                dirname($mixedSchemaPath),
                array_merge($mixinStack, [$mixedSchemaPath])
            );
            $mergedSchema = $this->mergeMixedSchema($mergedSchema, $mixedSchema, $mixedSchemaPath);
        }

        return $mergedSchema;
    }

    private function resolveMixinPath(string $mixinPath, ?string $baseDirectory): string
    {
        if ($mixinPath === '') {
            throw new \InvalidArgumentException('JSON Schema $mixin path must not be empty.');
        }

        $resolvedPath = str_starts_with($mixinPath, '/') || str_starts_with($mixinPath, 'phar://')
            ? $mixinPath
            : ($baseDirectory === null ? $mixinPath : $baseDirectory . '/' . $mixinPath);
        $normalizedPath = $this->normalizePath($resolvedPath);

        if (!file_exists($normalizedPath)) {
            throw new \InvalidArgumentException(sprintf('Mixed JSON Schema does not exist: %s', $normalizedPath));
        }

        return $normalizedPath;
    }

    private function normalizePath(string $path): string
    {
        $prefix = '';
        if (str_starts_with($path, 'phar://')) {
            $prefix = 'phar://';
            $path = substr($path, 7);
        }

        $isAbsolute = str_starts_with($path, '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments !== [] && end($segments) !== '..') {
                    array_pop($segments);
                    continue;
                }

                if (!$isAbsolute) {
                    $segments[] = $segment;
                }

                continue;
            }

            $segments[] = $segment;
        }

        return $prefix . ($isAbsolute ? '/' : '') . implode('/', $segments);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $mixedSchema
     * @return array<string, mixed>
     */
    private function mergeMixedSchema(array $schema, array $mixedSchema, string $mixedSchemaPath): array
    {
        if (!isset($mixedSchema['$defs'])) {
            return $schema;
        }

        if (!is_array($mixedSchema['$defs']) || array_is_list($mixedSchema['$defs'])) {
            throw new \InvalidArgumentException(
                sprintf('Mixed JSON Schema $defs must be an object: %s', $mixedSchemaPath)
            );
        }

        if (!isset($schema['$defs'])) {
            $schema['$defs'] = [];
        }

        if (!is_array($schema['$defs']) || array_is_list($schema['$defs'])) {
            throw new \InvalidArgumentException('JSON Schema $defs must be an object.');
        }

        foreach ($mixedSchema['$defs'] as $name => $definition) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException(
                    sprintf('Mixed JSON Schema $defs contains an invalid key: %s', $mixedSchemaPath)
                );
            }

            if (isset($schema['$defs'][$name])) {
                throw new \InvalidArgumentException(
                    sprintf('Duplicate JSON Schema definition "%s" mixed from %s.', $name, $mixedSchemaPath)
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

            if (($schema['$include'] ?? true) === false) {
                $errors[] = sprintf('%s.%s is not an allowed property.', $path, $property);
                continue;
            }

            if (isset($schema['$include']) && is_array($schema['$include'])) {
                $errors = array_merge(
                    $errors,
                    $this->validateAgainstSchema(
                        $propertyValue,
                        $schema['$include'],
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
