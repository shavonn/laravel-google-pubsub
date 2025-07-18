<?php

declare(strict_types=1);

namespace SysMatter\GooglePubSub\Exceptions;

use Throwable;

class SchemaValidationException extends PubSubException
{
    /**
     * The validation errors.
     */
    protected array $errors = [];

    /**
     * Create a new schema validation exception.
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null, array $errors = [])
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get the validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
