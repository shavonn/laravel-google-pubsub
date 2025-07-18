<?php

declare(strict_types=1);

namespace SysMatter\GooglePubSub\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use ReflectionClass;
use SysMatter\GooglePubSub\Attributes\PublishTo;
use SysMatter\GooglePubSub\PubSubManager;
use SysMatter\GooglePubSub\Contracts\ShouldPublishToPubSub;
use Illuminate\Support\Facades\Log;

class PubSubEventDispatcher
{
    /**
     * The PubSub manager instance.
     */
    protected PubSubManager $pubsub;

    /**
     * The event dispatcher instance.
     */
    protected Dispatcher $events;

    /**
     * The configuration array.
     */
    protected array $config;

    /**
     * Events currently being dispatched (to prevent loops).
     */
    protected array $dispatching = [];

    /**
     * Create a new PubSub event dispatcher.
     */
    public function __construct(PubSubManager $pubsub, Dispatcher $events, array $config)
    {
        $this->pubsub = $pubsub;
        $this->events = $events;
        $this->config = $config;
    }

    /**
     * Register the event listener.
     */
    public function register(): void
    {
        if (!($this->config['events']['enabled'] ?? false)) {
            return;
        }

        $this->events->listen('*', [$this, 'handleEvent']);
    }

    /**
     * Handle an event dispatch.
     */
    public function handleEvent(string $eventName, array $payload): void
    {
        // Prevent infinite loops
        if (in_array($eventName, $this->dispatching)) {
            return;
        }

        // Get the event object
        $event = $payload[0] ?? null;
        if (!is_object($event)) {
            return;
        }

        // Check if this event should be published
        if (!$this->shouldPublish($eventName, $event)) {
            return;
        }

        try {
            $this->dispatching[] = $eventName;
            $this->publishEvent($eventName, $event);
        } finally {
            $key = array_search($eventName, $this->dispatching);
            if ($key !== false) {
                unset($this->dispatching[$key]);
            }
        }
    }

    /**
     * Check if an event should be published to Pub/Sub.
     */
    protected function shouldPublish(string $eventName, object $event): bool
    {
        // Check if event implements the interface
        if ($event instanceof ShouldPublishToPubSub) {
            return true;
        }

        // Check explicit event list
        $publishEvents = $this->config['events']['publish'] ?? [];
        if (in_array(get_class($event), $publishEvents)) {
            return true;
        }

        // Check patterns
        $patterns = $this->config['events']['publish_patterns'] ?? [];
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, get_class($event))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Publish an event to Pub/Sub.
     */
    protected function publishEvent(string $eventName, object $event): void
    {
        $topic = $this->getTopicForEvent($event);

        $data = [
            'event' => $eventName,
            'class' => get_class($event),
            'data' => $this->serializeEvent($event),
            'timestamp' => now()->toIso8601String(),
        ];

        $attributes = [
            'source' => 'laravel',
            'event_name' => $eventName,
            'event_class' => get_class($event),
        ];

        // Add custom attributes if event provides them
        if (method_exists($event, 'pubsubAttributes')) {
            $attributes = array_merge($attributes, $event->pubsubAttributes());
        }

        // Add ordering key if provided
        $options = [];
        if (method_exists($event, 'pubsubOrderingKey')) {
            $options['ordering_key'] = $event->pubsubOrderingKey();
        }

        $messageId = $this->pubsub->publish($topic, $data, $attributes, $options);

        if ($this->config['monitoring']['log_published_messages'] ?? false) {
            Log::info('Published Laravel event to Pub/Sub', [
                'event' => $eventName,
                'topic' => $topic,
                'message_id' => $messageId,
            ]);
        }
    }

    /**
     * Get the topic for an event.
     */
    protected function getTopicForEvent(object $event): string
    {
        // Check if event specifies its topic
        if (method_exists($event, 'pubsubTopic')) {
            return $event->pubsubTopic();
        }

        // Check if event has PublishTo attribute
        $reflection = new ReflectionClass($event);
        $attributes = $reflection->getAttributes(PublishTo::class);
        if (!empty($attributes)) {
            return $attributes[0]->newInstance()->topic;
        }

        // Check topic mappings
        foreach ($this->config['topics'] ?? [] as $topic => $topicConfig) {
            $events = $topicConfig['events'] ?? [];
            if (in_array(get_class($event), $events)) {
                return $topic;
            }
        }

        // Default topic
        return $this->config['events']['default_topic'] ?? 'laravel-events';
    }

    /**
     * Serialize an event for publishing.
     */
    protected function serializeEvent(object $event): array
    {
        if (method_exists($event, 'toPubSub')) {
            return $event->toPubSub();
        }

        if (method_exists($event, 'toArray')) {
            return $event->toArray();
        }

        // Default serialization - get public properties
        return get_object_vars($event);
    }
}
