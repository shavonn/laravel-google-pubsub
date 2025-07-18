<?php

declare(strict_types=1);

namespace SysMatter\GooglePubSub\Messages;

/**
 * Mock message class for webhook compatibility.
 *
 * This class mimics the Google\Cloud\PubSub\Message interface
 * for messages received via webhooks.
 */
class WebhookMessage
{
    /**
     * Create a new webhook message instance.
     */
    public function __construct(
        protected string $id,
        protected string $data,
        protected array $attributes,
        protected string $publishTime
    ) {
    }

    /**
     * Get the message ID.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the message data.
     */
    public function data(): string
    {
        return $this->data;
    }

    /**
     * Get the message attributes.
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the publish time.
     */
    public function publishTime(): string
    {
        return $this->publishTime;
    }

    /**
     * Get the subscription name (not available for webhooks).
     */
    public function subscription(): ?string
    {
        return $this->attributes['subscription'] ?? null;
    }

    /**
     * Get the ordering key if present.
     */
    public function orderingKey(): ?string
    {
        return $this->attributes['ordering_key'] ?? null;
    }
}
