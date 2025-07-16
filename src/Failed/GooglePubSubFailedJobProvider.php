<?php

namespace Shavonn\GooglePubSub\Failed;

use Exception;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Date;
use Shavonn\GooglePubSub\Exceptions\GooglePubSubException;
use Throwable;

class GooglePubSubFailedJobProvider implements FailedJobProviderInterface
{
    /**
     * The configuration array.
     *
     * @var array
     */
    protected $config;

    /**
     * The Pub/Sub client instance.
     *
     * @var PubSubClient|null
     */
    protected $pubsub;

    /**
     * Create a new Pub/Sub failed job provider.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  Throwable  $exception
     * @return int|null
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $failedAt = Date::now();

        $topic = $this->getFailedJobsTopic();

        $messageData = [
            'data' => json_encode([
                'connection' => $connection,
                'queue' => $queue,
                'payload' => $payload,
                'exception' => (string) $exception,
                'failed_at' => $failedAt->toIso8601String(),
            ]),
            'attributes' => [
                'connection' => $connection,
                'queue' => $queue,
                'failed_at' => $failedAt->timestamp,
                'exception_class' => get_class($exception),
            ],
        ];

        try {
            $result = $topic->publish($messageData);

            if ($this->config['monitoring']['log_failed_messages'] ?? true) {
                logger()->error('Job failed and logged to Pub/Sub', [
                    'connection' => $connection,
                    'queue' => $queue,
                    'message_id' => $result['messageIds'][0] ?? null,
                    'exception' => $exception->getMessage(),
                ]);
            }

            return $result['messageIds'][0] ?? null;
        } catch (Exception $e) {
            throw new GooglePubSubException(
                "Failed to log failed job: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array
     */
    public function all()
    {
        // This would require maintaining a separate storage mechanism
        // as Pub/Sub doesn't provide a way to list historical messages
        return [];
    }

    /**
     * Get a single failed job.
     *
     * @param  mixed  $id
     * @return object|null
     */
    public function find($id)
    {
        // This would require maintaining a separate storage mechanism
        return null;
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param  mixed  $id
     * @return bool
     */
    public function forget($id)
    {
        // This would require maintaining a separate storage mechanism
        return false;
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @param  int|null  $hours
     * @return void
     */
    public function flush($hours = null)
    {
        // This would require maintaining a separate storage mechanism
    }

    /**
     * Get the Pub/Sub client instance.
     */
    protected function getPubSubClient(): PubSubClient
    {
        if (! $this->pubsub) {
            $pubsubConfig = [
                'projectId' => $this->config['project_id'],
            ];

            if ($this->config['auth_method'] === 'key_file' && ! empty($this->config['key_file'])) {
                $pubsubConfig['keyFilePath'] = $this->config['key_file'];
            }

            $this->pubsub = new PubSubClient($pubsubConfig);
        }

        return $this->pubsub;
    }

    /**
     * Get the IDs of all failed jobs.
     *
     * @param  string|null  $queue
     * @return array
     */
    public function ids($queue = null)
    {
        // This would require maintaining a separate storage mechanism
        // as Pub/Sub doesn't provide a way to list historical messages
        return [];
    }

    /**
     * Get the failed jobs topic.
     *
     * @return Topic
     */
    protected function getFailedJobsTopic()
    {
        $client = $this->getPubSubClient();
        $topicName = 'laravel-failed-jobs';

        $topic = $client->topic($topicName);

        if (! $topic->exists()) {
            $topic->create();
        }

        return $topic;
    }
}
