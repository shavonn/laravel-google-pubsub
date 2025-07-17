<?php

declare(strict_types=1);

namespace Shavonn\GooglePubSub\Schema;

use Shavonn\GooglePubSub\Exceptions\SchemaValidationException;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;

class SchemaValidator
{
    /**
     * The JSON Schema validator instance.
     */
    protected Validator $validator;

    /**
     * Loaded schemas cache.
     */
    protected array $schemas = [];

    /**
     * Schema configuration.
     */
    protected array $config;

    /**
     * Create a new schema validator.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->validator = new Validator();
    }

    /**
     * Validate data against a schema.
     *
     * @param mixed $data
     * @param string $schemaName
     * @throws SchemaValidationException
     */
    public function validate($data, string $schemaName): void
    {
        $schema = $this->getSchema($schemaName);

        if (!$schema) {
            if ($this->config['strict_mode'] ?? true) {
                throw new SchemaValidationException("Schema '{$schemaName}' not found");
            }
            return;
        }

        $result = $this->validator->validate($data, $schema);

        if (!$result->isValid()) {
            $formatter = new ErrorFormatter();
            $errors = $formatter->format($result->error());

            throw new SchemaValidationException(
                "Validation failed for schema '{$schemaName}': " . json_encode($errors),
                errors: $errors
            );
        }
    }

    /**
     * Check if data is valid against a schema.
     */
    public function isValid($data, string $schemaName): bool
    {
        try {
            $this->validate($data, $schemaName);
            return true;
        } catch (SchemaValidationException $e) {
            return false;
        }
    }

    /**
     * Get validation errors without throwing exception.
     */
    public function getErrors($data, string $schemaName): ?array
    {
        $schema = $this->getSchema($schemaName);

        if (!$schema) {
            return null;
        }

        $result = $this->validator->validate($data, $schema);

        if (!$result->isValid()) {
            $formatter = new ErrorFormatter();
            return $formatter->format($result->error());
        }

        return null;
    }

    /**
     * Load a schema.
     */
    protected function getSchema(string $schemaName): ?object
    {
        if (isset($this->schemas[$schemaName])) {
            return $this->schemas[$schemaName];
        }

        $schemaConfig = $this->config['schemas'][$schemaName] ?? null;

        if (!$schemaConfig) {
            return null;
        }

        $schema = $this->loadSchemaFromConfig($schemaConfig);
        $this->schemas[$schemaName] = $schema;

        return $schema;
    }

    /**
     * Load schema from configuration.
     */
    protected function loadSchemaFromConfig(array $config): object
    {
        // Load from file
        if (isset($config['file'])) {
            $path = $config['file'];

            if (!file_exists($path)) {
                $path = base_path($path);
            }

            if (!file_exists($path)) {
                throw new SchemaValidationException("Schema file not found: {$config['file']}");
            }

            $content = file_get_contents($path);
            $decoded = json_decode($content);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new SchemaValidationException("Invalid JSON in schema file: " . json_last_error_msg());
            }

            return $decoded;
        }

        // Load from array
        if (isset($config['schema'])) {
            return json_decode(json_encode($config['schema']));
        }

        // Load from URL
        if (isset($config['url'])) {
            $content = file_get_contents($config['url']);
            $decoded = json_decode($content);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new SchemaValidationException("Invalid JSON from URL: " . json_last_error_msg());
            }

            return $decoded;
        }

        throw new SchemaValidationException('Schema configuration must include file, schema, or url');
    }

    /**
     * Register a schema programmatically.
     */
    public function registerSchema(string $name, $schema): void
    {
        if (is_string($schema)) {
            $schema = json_decode($schema);
        } elseif (is_array($schema)) {
            $schema = json_decode(json_encode($schema));
        }

        $this->schemas[$name] = $schema;
    }

    /**
     * Clear schema cache.
     */
    public function clearCache(): void
    {
        $this->schemas = [];
    }
}
