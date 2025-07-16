<?php

declare(strict_types=1);

namespace Shavonn\GooglePubSub\Subscriber;

use Closure;
use Exception;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Subscription;
use Illuminate\Support\Facades\Log;
use Shavonn\GooglePubSub\Contracts\MessageFormatter;
use Shavonn\GooglePubSub\Exceptions\SubscriptionException;
use Shavonn\GooglePubSub\Formatters\JsonFormatter;

class Subscriber
{
    /**
     * The PubSub client instance.
     */
    protected PubSubClient $client;

    /**
     * The subscription name.
     */
    protected string $subscriptionName;

    /**
     * The topic name.
     */
    protected ?string $topicName;

    /**
     * The configuration array.
     */
    protected array $config;

    /**
     * The message formatter.
     */
    protected MessageFormatter $formatter;

    /**
     * The subscription instance.
     */
    protected ?Subscription $subscription = null;

    /**
     * Message handler callbacks.
     */
    protected array $handlers = [];

    /**
     * Error handler callback.
     */
    protected ?Closure $errorHandler = null;

    /**
     * Create a new subscriber instance.
     */
    public function __construct(
        PubSubClient $client,
        string $subscriptionName,
        ?string $topicName = null,
        array $config = []
    ) {
        $this->client = $client;
        $this->subscriptionName = $subscriptionName;
        $this->topicName = $topicName;
        $this->config = $config;
        $this->formatter = new JsonFormatter();
    }

    /**
     * Set the topic for this subscription.
     */
    public function topic(string $topicName): self
    {
        $this->topicName = $topicName;

        return $this;
    }

    /**
     * Add a message handler.
     */
    public function handler(callable $handler): self
    {
        $this->handlers[] = $handler;

        return $this;
    }

    /**
     * Set the error handler.
     */
    public function onError(callable $handler): self
    {
        $this->errorHandler = $handler;

        return $this;
    }

    /**
     * Pull messages from the subscription.
     */
    public function pull(int $maxMessages = 10): array
    {
        try {
            $subscription = $this->getSubscription();

            $messages = $subscription->pull([
                'maxMessages' => $maxMessages,
                'returnImmediately' => true,
            ]);

            $results = [];

            foreach ($messages as $message) {
                try {
                    $results[] = $this->processMessage($message);
                } catch (Exception $e) {
                    $this->handleError($e, $message);
                }
            }

            return $results;
        } catch (Exception $e) {
            throw new SubscriptionException(
                "Failed to pull messages: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Continuously pull and process messages.
     */
    public function listen(array $options = []): void
    {
        $maxMessages = $options['max_messages'] ?? 10;
        $waitTime = $options['wait_time'] ?? 1;

        while (true) {
            try {
                $messages = $this->pull($maxMessages);

                if (empty($messages) && $waitTime > 0) {
                    sleep($waitTime);
                }

                // Check for stop signal
                if ($this->shouldStop()) {
                    break;
                }
            } catch (Exception $e) {
                $this->handleError($e);

                // Wait before retrying
                sleep($waitTime);
            }
        }
    }

    /**
     * Acknowledge a message.
     */
    public function acknowledge(Message $message): void
    {
        $this->getSubscription()->acknowledge($message);

        if ($this->config['monitoring']['log_consumed_messages'] ?? false) {
            Log::info('Acknowledged Pub/Sub message', [
                'subscription' => $this->subscriptionName,
                'message_id' => $message->id(),
            ]);
        }
    }

    /**
     * Acknowledge multiple messages.
     */
    public function acknowledgeBatch(array $messages): void
    {
        $this->getSubscription()->acknowledgeBatch($messages);
    }

    /**
     * Modify the acknowledgment deadline.
     */
    public function modifyAckDeadline(Message $message, int $seconds): void
    {
        $this->getSubscription()->modifyAckDeadline($message, $seconds);
    }

    /**
     * Get or create the subscription instance.
     */
    protected function getSubscription(): Subscription
    {
        if (! $this->subscription) {
            $subscription = $this->client->subscription($this->subscriptionName);

            // Auto-create subscription if configured
            if (($this->config['auto_create_subscriptions'] ?? true) && ! $subscription->exists()) {
                if (! $this->topicName) {
                    throw new SubscriptionException(
                        "Cannot create subscription '{$this->subscriptionName}' without a topic name"
                    );
                }

                $topic = $this->client->topic($this->topicName);

                if (! $topic->exists() && ($this->config['auto_create_topics'] ?? true)) {
                    $topic->create();
                }

                $subscriptionConfig = $this->getSubscriptionConfig();
                $subscription = $topic->subscribe($this->subscriptionName, $subscriptionConfig);

                Log::info("Created Pub/Sub subscription: {$this->subscriptionName}");
            }

            $this->subscription = $subscription;
        }

        return $this->subscription;
    }

    /**
     * Process a single message.
     */
    protected function processMessage(Message $message): mixed
    {
        $data = $this->decodeMessage($message);

        // Log if configured
        if ($this->config['monitoring']['log_consumed_messages'] ?? false) {
            Log::info('Processing Pub/Sub message', [
                'subscription' => $this->subscriptionName,
                'message_id' => $message->id(),
                'attributes' => $message->attributes(),
            ]);
        }

        $result = null;

        // Call all handlers
        foreach ($this->handlers as $handler) {
            $result = $handler($data, $message);
        }

        // Auto-acknowledge if configured
        if ($this->config['auto_acknowledge'] ?? true) {
            $this->acknowledge($message);
        }

        return $result;
    }

    /**
     * Decode a message.
     */
    protected function decodeMessage(Message $message): mixed
    {
        $data = $message->data();
        $attributes = $message->attributes();

        // Decompress if needed
        if (($attributes['compressed'] ?? false) === 'true') {
            $data = gzuncompress($data);
            if ($data === false) {
                throw new SubscriptionException('Failed to decompress message data');
            }
        }

        // Parse the data
        return $this->formatter->parse($data);
    }

    /**
     * Handle an error.
     */
    protected function handleError(Exception $e, ?Message $message = null): void
    {
        if ($this->errorHandler) {
            ($this->errorHandler)($e, $message);
        } else {
            Log::error('Pub/Sub subscriber error', [
                'subscription' => $this->subscriptionName,
                'error' => $e->getMessage(),
                'message_id' => $message?->id(),
            ]);
        }
    }

    /**
     * Check if we should stop listening.
     */
    protected function shouldStop(): bool
    {
        // Could check for signals, cache flags, etc.
        return false;
    }

    /**
     * Get subscription configuration.
     */
    protected function getSubscriptionConfig(): array
    {
        $config = [
            'ackDeadlineSeconds' => $this->config['ack_deadline'] ?? 60,
        ];

        // Configure retry policy
        if (isset($this->config['retry_policy'])) {
            $config['retryPolicy'] = $this->config['retry_policy'];
        }

        // Configure dead letter policy
        if ($this->config['dead_letter_policy']['enabled'] ?? false) {
            $deadLetterTopic = $this->topicName.($this->config['dead_letter_policy']['dead_letter_topic_suffix'] ?? '-dead-letter');

            $dlTopic = $this->client->topic($deadLetterTopic);
            if (! $dlTopic->exists()) {
                $dlTopic->create();
            }

            $config['deadLetterPolicy'] = [
                'deadLetterTopic' => $dlTopic->name(),
                'maxDeliveryAttempts' => $this->config['dead_letter_policy']['max_delivery_attempts'] ?? 5,
            ];
        }

        return $config;
    }
}
