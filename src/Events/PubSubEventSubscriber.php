<?php

declare(strict_types=1);

namespace Shavonn\GooglePubSub\Events;

use Exception;
use Google\Cloud\PubSub\Message;
use Illuminate\Contracts\Events\Dispatcher;
use ReflectionClass;
use Shavonn\GooglePubSub\PubSubManager;
use Shavonn\GooglePubSub\Messages\WebhookMessage;
use Illuminate\Support\Facades\Log;

class PubSubEventSubscriber
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
     * Active subscriptions.
     */
    protected array $subscriptions = [];

    /**
     * Create a new PubSub event subscriber.
     */
    public function __construct(PubSubManager $pubsub, Dispatcher $events, array $config)
    {
        $this->pubsub = $pubsub;
        $this->events = $events;
        $this->config = $config;
    }

    /**
     * Subscribe to a topic and dispatch events.
     */
    public function subscribe(string $subscriptionName, string $topic, array $options = []): void
    {
        $subscriber = $this->pubsub->subscriber($subscriptionName, $topic);

        $subscriber->handler(function ($data, Message $message) use ($topic) {
            $this->handleMessage($data, $message, $topic);
        });

        $subscriber->onError(function (Exception $e, ?Message $message) use ($topic) {
            Log::error('PubSub event subscriber error', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'message_id' => $message?->id(),
            ]);
        });

        $this->subscriptions[$subscriptionName] = $subscriber;

        // Start listening based on options
        if ($options['async'] ?? true) {
            // Queue the listener as a job
            dispatch(function () use ($subscriber, $options) {
                $subscriber->listen($options);
            });
        } else {
            // Listen synchronously
            $subscriber->listen($options);
        }
    }

    /**
     * Subscribe to multiple topics based on configuration.
     */
    public function subscribeToConfiguredTopics(): void
    {
        foreach ($this->config['topics'] ?? [] as $topic => $topicConfig) {
            if (!($topicConfig['subscribe'] ?? true)) {
                continue;
            }

            $subscriptionName = $topic . ($this->config['subscription_suffix'] ?? '-laravel-events');

            $this->subscribe($subscriptionName, $topic, $topicConfig['subscription_options'] ?? []);
        }
    }

    /**
     * Handle an incoming message.
     */
    public function handleMessage(array $data, Message|WebhookMessage $message, string $topic): void
    {
        try {
            // Check if it's a Laravel event from another service
            if ($this->isLaravelEvent($data)) {
                $this->dispatchLaravelEvent($data, $message);
                return;
            }

            // Dispatch as a generic PubSub event
            $this->dispatchGenericEvent($data, $message, $topic);
        } catch (Exception $e) {
            Log::error('Failed to handle PubSub message as event', [
                'topic' => $topic,
                'message_id' => $message->id(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if the message is a Laravel event.
     */
    protected function isLaravelEvent(array $data): bool
    {
        return isset($data['event']) && isset($data['class']) && isset($data['data']);
    }

    /**
     * Dispatch a Laravel event from a message.
     */
    protected function dispatchLaravelEvent(array $data, Message|WebhookMessage $message): void
    {
        $eventClass = $data['class'];
        $eventData = $data['data'];

        // Check if we should handle this event class
        if (!$this->shouldHandleEventClass($eventClass)) {
            return;
        }

        // Try to reconstruct the event
        if (class_exists($eventClass)) {
            try {
                $event = $this->reconstructEvent($eventClass, $eventData, $message);
                $this->events->dispatch($event);

                Log::info('Dispatched event from PubSub', [
                    'event' => $eventClass,
                    'message_id' => $message->id(),
                ]);
            } catch (Exception $e) {
                // Fall back to generic event
                $this->events->dispatch("pubsub.unknown.{$eventClass}", [
                    'data' => $eventData,
                    'message' => $message,
                    'topic' => 'unknown',
                ]);
            }
        } else {
            // Dispatch as a generic event with the original class name
            $this->events->dispatch('pubsub.event.received', [
                'event_class' => $eventClass,
                'data' => $eventData,
                'message' => $message,
            ]);
        }
    }

    /**
     * Dispatch a generic PubSub event.
     */
    protected function dispatchGenericEvent(array $data, Message|WebhookMessage $message, string $topic): void
    {
        // Get event type from attributes or data
        $attributes = $message->attributes();
        $eventType = $attributes['event_type'] ?? $data['event'] ?? 'message';

        // Dispatch topic-specific event
        $this->events->dispatch("pubsub.{$topic}.{$eventType}", [
            'data' => $data,
            'message' => $message,
            'topic' => $topic,
        ]);

        // Dispatch generic received event
        $this->events->dispatch('pubsub.message.received', [
            'data' => $data,
            'message' => $message,
            'topic' => $topic,
            'event_type' => $eventType,
        ]);
    }

    /**
     * Check if we should handle an event class.
     */
    protected function shouldHandleEventClass(string $eventClass): bool
    {
        // Check if in allowed list
        $allowedClasses = $this->config['events']['handle_classes'] ?? [];
        if (!empty($allowedClasses)) {
            return in_array($eventClass, $allowedClasses);
        }

        // Check if in denied list
        $deniedClasses = $this->config['events']['ignore_classes'] ?? [];
        return !in_array($eventClass, $deniedClasses);
    }

    /**
     * Reconstruct an event from data.
     */
    protected function reconstructEvent(string $eventClass, array $data, Message|WebhookMessage $message)
    {
        // Check if the event class has a fromPubSub method
        if (method_exists($eventClass, 'fromPubSub')) {
            return call_user_func([$eventClass, 'fromPubSub'], $data, $message);
        }

        // Try to create with constructor
        $reflection = new ReflectionClass($eventClass);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $eventClass();
        }

        // Map data to constructor parameters
        $parameters = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $data)) {
                $parameters[] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $parameters[] = $param->getDefaultValue();
            } else {
                throw new Exception("Cannot reconstruct event: missing required parameter '{$name}'");
            }
        }

        return $reflection->newInstanceArgs($parameters);
    }

    /**
     * Stop all subscriptions.
     */
    public function stop(): void
    {
        foreach ($this->subscriptions as $subscription) {
            // This would need to implement a stop mechanism
            // For now, subscriptions run until the process ends
        }
    }
}
