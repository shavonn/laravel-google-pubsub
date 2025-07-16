<?php

namespace Shavonn\GooglePubSub\Queue;

use DateInterval;
use DateTimeInterface;
use Exception;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Subscription;
use Google\Cloud\PubSub\Topic;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Shavonn\GooglePubSub\Exceptions\GooglePubSubException;
use Shavonn\GooglePubSub\Queue\Jobs\GooglePubSubJob;

class GooglePubSubQueue extends Queue implements QueueContract
{
    /**
     * The Pub/Sub client instance.
     */
    protected PubSubClient $pubsub;

    /**
     * The name of the default queue.
     */
    protected string $default;

    /**
     * The queue configuration options.
     *
     * @var array
     */
    protected array|null|object $options;

    /**
     * Create a new Pub/Sub queue instance.
     */
    public function __construct(PubSubClient $pubsub, string $default = 'default', array $options = [])
    {
        $this->pubsub = $pubsub;
        $this->default = $default;
        $this->options = array_merge(config('pubsub.queue_options', []), $options);
        $this->connectionName = 'pubsub';
    }

    /**
     * Get the size of the queue.
     *
     * @param  string|null  $queue
     */
    public function size($queue = null): int
    {
        // Pub/Sub doesn't provide a direct way to get queue size
        // This would need to be implemented using monitoring APIs
        return 0;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     *
     * @throws GooglePubSubException
     */
    public function push($job, $data = '', $queue = null): mixed
    {
        return $this->pushRaw($this->createPayload($job, $this->getQueue($queue), $data), $queue);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     *
     * @throws GooglePubSubException
     */
    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $topic = $this->getTopic($this->getQueue($queue));

        $messageData = $this->prepareMessage($payload, $options);

        try {
            $message = $topic->publish($messageData);

            if ($this->options['monitoring']['log_published_messages'] ?? false) {
                logger()->info('Published message to Pub/Sub', [
                    'topic' => $topic->name(),
                    'message_id' => $message['messageIds'][0] ?? null,
                    'size' => strlen($payload),
                ]);
            }

            return $message['messageIds'][0] ?? null;
        } catch (Exception $e) {
            throw new GooglePubSubException(
                "Failed to publish message: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  DateTimeInterface|DateInterval|int  $delay
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     */
    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        $payload = $this->createPayload($job, $this->getQueue($queue), $data);
        $delay = $this->availableAt($delay);

        return $this->pushRaw($payload, $queue, [
            'delay' => $delay - $this->currentTime(),
        ]);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string|null  $queue
     */
    public function pop($queue = null): ?Job
    {
        $subscription = $this->getSubscription($this->getQueue($queue));

        $messages = $subscription->pull([
            'maxMessages' => $this->options['max_messages'] ?? 1,
            'returnImmediately' => false,
        ]);

        if (empty($messages)) {
            return null;
        }

        $message = reset($messages);

        if ($this->options['monitoring']['log_consumed_messages'] ?? false) {
            logger()->info('Consumed message from Pub/Sub', [
                'subscription' => $subscription->name(),
                'message_id' => $message->id(),
            ]);
        }

        return new GooglePubSubJob(
            $this->container,
            $this,
            $message,
            $subscription,
            $this->connectionName,
            $this->getQueue($queue)
        );
    }

    /**
     * Delete a message from the Pub/Sub queue.
     */
    public function deleteMessage(string $queue, GooglePubSubJob $job): void
    {
        $job->delete();
    }

    /**
     * Get the queue or return the default.
     */
    public function getQueue(?string $queue): string
    {
        return $queue ?: $this->default;
    }

    /**
     * Get a Pub/Sub topic instance.
     */
    protected function getTopic(string $queue): Topic
    {
        $topic = $this->pubsub->topic($queue);

        if ($this->options['auto_create_topics'] ?? true) {
            if (! $topic->exists()) {
                $topic->create($this->getTopicConfig($queue));
            }
        }

        return $topic;
    }

    /**
     * Get a Pub/Sub subscription instance.
     */
    protected function getSubscription(string $queue): Subscription
    {
        $subscriptionName = $queue.($this->options['subscription_suffix'] ?? '-laravel');
        $subscription = $this->pubsub->subscription($subscriptionName);

        if ($this->options['auto_create_subscriptions'] ?? true) {
            if (! $subscription->exists()) {
                $topic = $this->getTopic($queue);
                $subscription = $topic->subscribe($subscriptionName, $this->getSubscriptionConfig($queue));
            }
        }

        return $subscription;
    }

    /**
     * Prepare a message for publishing.
     */
    protected function prepareMessage(string $payload, array $options = []): array
    {
        $data = $payload;
        $attributes = [];

        // Compress if needed
        if (($this->options['message_options']['compress_payload'] ?? false)
            && strlen($payload) > ($this->options['message_options']['compression_threshold'] ?? 1024)) {
            $data = gzcompress($payload);
            $attributes['compressed'] = 'true';
        }

        // Add metadata
        if ($this->options['message_options']['add_metadata'] ?? true) {
            $attributes['laravel_queue'] = $this->connectionName;
            $attributes['published_at'] = (string) $this->currentTime();
            $attributes['hostname'] = gethostname();
        }

        // Handle delay
        if (isset($options['delay']) && $options['delay'] > 0) {
            $attributes['deliver_after'] = (string) ($this->currentTime() + $options['delay']);
        }

        // Handle ordering
        if (isset($options['ordering_key']) && ($this->options['enable_message_ordering'] ?? false)) {
            $attributes['ordering_key'] = $options['ordering_key'];
        }

        // Merge custom attributes
        if (isset($options['attributes'])) {
            $attributes = array_merge($attributes, $options['attributes']);
        }

        return compact('data', 'attributes');
    }

    /**
     * Get topic configuration.
     */
    protected function getTopicConfig(string $queue): array
    {
        $config = [];

        if ($this->options['enable_message_ordering'] ?? false) {
            $config['enableMessageOrdering'] = true;
        }

        return $config;
    }

    /**
     * Get subscription configuration.
     */
    protected function getSubscriptionConfig(string $queue): array
    {
        $config = [
            'ackDeadlineSeconds' => $this->options['ack_deadline'] ?? 60,
        ];

        // Configure retry policy
        if (isset($this->options['retry_policy'])) {
            $config['retryPolicy'] = $this->options['retry_policy'];
        }

        // Configure dead letter policy
        if (($this->options['dead_letter_policy']['enabled'] ?? false)) {
            $deadLetterTopic = $queue.($this->options['dead_letter_policy']['dead_letter_topic_suffix'] ?? '-dead-letter');

            // Ensure dead letter topic exists
            $dlTopic = $this->pubsub->topic($deadLetterTopic);
            if (! $dlTopic->exists()) {
                $dlTopic->create();
            }

            $config['deadLetterPolicy'] = [
                'deadLetterTopic' => $dlTopic->name(),
                'maxDeliveryAttempts' => $this->options['dead_letter_policy']['max_delivery_attempts'] ?? 5,
            ];
        }

        // Enable message ordering
        if ($this->options['enable_message_ordering'] ?? false) {
            $config['enableMessageOrdering'] = true;
        }

        return $config;
    }

    /**
     * Create a payload array from the given job and data.
     *
     * @param  string|object  $job
     * @param  string  $queue
     * @param  mixed  $data
     */
    protected function createPayloadArray($job, $queue, $data = ''): array
    {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'attempts' => 0,
        ]);
    }
}
