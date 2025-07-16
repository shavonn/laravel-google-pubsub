<?php

declare(strict_types=1);

namespace Shavonn\GooglePubSub\Publisher;

use Exception;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Illuminate\Support\Facades\Log;
use Shavonn\GooglePubSub\Contracts\MessageFormatter;
use Shavonn\GooglePubSub\Exceptions\PublishException;
use Shavonn\GooglePubSub\Formatters\JsonFormatter;

class Publisher
{
    /**
     * The PubSub client instance.
     */
    protected PubSubClient $client;

    /**
     * The configuration array.
     */
    protected array $config;

    /**
     * The message formatter.
     */
    protected MessageFormatter $formatter;

    /**
     * Cached topic instances.
     */
    protected array $topics = [];

    /**
     * Create a new publisher instance.
     */
    public function __construct(PubSubClient $client, array $config = [])
    {
        $this->client = $client;
        $this->config = $config;
        $this->formatter = new JsonFormatter();
    }

    /**
     * Publish a message to a topic.
     *
     * @param  mixed  $data
     * @return string Message ID
     *
     * @throws PublishException
     */
    public function publish(string $topicName, $data, array $attributes = [], array $options = []): string
    {
        try {
            $topic = $this->getTopic($topicName);

            // Format the message
            $message = $this->prepareMessage($topicName, $data, $attributes, $options);

            // Publish the message
            $result = $topic->publish($message);

            $messageId = $result['messageIds'][0] ?? null;

            if (! $messageId) {
                throw new PublishException('Failed to get message ID from publish result');
            }

            // Log if configured
            if ($this->config['monitoring']['log_published_messages'] ?? false) {
                Log::info('Published message to Pub/Sub', [
                    'topic' => $topicName,
                    'message_id' => $messageId,
                    'attributes' => $attributes,
                ]);
            }

            return $messageId;
        } catch (Exception $e) {
            throw new PublishException(
                "Failed to publish message to topic '{$topicName}': {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Publish multiple messages to a topic.
     *
     * @param  array  $messages  Array of ['data' => mixed, 'attributes' => array]
     * @return array Message IDs
     *
     * @throws PublishException
     */
    public function publishBatch(string $topicName, array $messages, array $options = []): array
    {
        try {
            $topic = $this->getTopic($topicName);

            $formattedMessages = [];
            foreach ($messages as $message) {
                $formattedMessages[] = $this->prepareMessage(
                    $topicName,
                    $message['data'] ?? null,
                    $message['attributes'] ?? [],
                    $options
                );
            }

            $result = $topic->publishBatch($formattedMessages);

            return $result['messageIds'] ?? [];
        } catch (Exception $e) {
            throw new PublishException(
                "Failed to publish batch to topic '{$topicName}': {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Set a custom message formatter.
     */
    public function setFormatter(MessageFormatter $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * Get or create a topic instance.
     */
    protected function getTopic(string $topicName): Topic
    {
        if (! isset($this->topics[$topicName])) {
            $topic = $this->client->topic($topicName);

            // Auto-create topic if configured
            if ($this->config['auto_create_topics'] ?? true) {
                if (! $topic->exists()) {
                    $topicConfig = $this->getTopicConfig($topicName);
                    $topic->create($topicConfig);

                    Log::info("Created Pub/Sub topic: {$topicName}");
                }
            }

            $this->topics[$topicName] = $topic;
        }

        return $this->topics[$topicName];
    }

    /**
     * Prepare a message for publishing.
     */
    protected function prepareMessage(string $topicName, $data, array $attributes, array $options): array
    {
        // Get topic-specific configuration
        $topicConfig = $this->config['topics'][$topicName] ?? [];

        // Apply schema validation if configured
        if (isset($topicConfig['schema'])) {
            $this->validateMessage($data, $topicConfig['schema']);
        }

        // Format the data
        $formattedData = $this->formatter->format($data);

        // Apply compression if needed
        if ($this->shouldCompress($formattedData, $options)) {
            $formattedData = gzcompress($formattedData);
            $attributes['compressed'] = 'true';
            $attributes['compression_type'] = 'gzip';
        }

        // Add metadata if configured
        if ($this->config['message_options']['add_metadata'] ?? true) {
            $attributes = array_merge($attributes, [
                'published_at' => (string) time(),
                'publisher' => 'laravel',
                'hostname' => gethostname(),
                'app_name' => config('app.name'),
            ]);
        }

        // Build the message
        $message = [
            'data' => $formattedData,
            'attributes' => $attributes,
        ];

        // Add ordering key if provided
        if (isset($options['ordering_key'])) {
            $message['orderingKey'] = $options['ordering_key'];
        }

        return $message;
    }

    /**
     * Validate message against schema.
     */
    protected function validateMessage($data, string $schemaName): void
    {
        // This will be implemented when we add schema support
        // For now, just return
    }

    /**
     * Determine if the message should be compressed.
     */
    protected function shouldCompress(string $data, array $options): bool
    {
        if (isset($options['compress'])) {
            return (bool) $options['compress'];
        }

        if (! ($this->config['message_options']['compress_payload'] ?? true)) {
            return false;
        }

        $threshold = $this->config['message_options']['compression_threshold'] ?? 1024;

        return strlen($data) > $threshold;
    }

    /**
     * Get topic configuration.
     */
    protected function getTopicConfig(string $topicName): array
    {
        $config = [];

        // Check if message ordering is enabled for this topic
        $topicSettings = $this->config['topics'][$topicName] ?? [];
        if ($topicSettings['enable_message_ordering'] ?? false) {
            $config['enableMessageOrdering'] = true;
        }

        return $config;
    }
}
