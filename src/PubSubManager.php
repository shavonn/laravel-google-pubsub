<?php

namespace Shavonn\GooglePubSub;

use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Support\Manager;
use Shavonn\GooglePubSub\Exceptions\PubSubException;
use Shavonn\GooglePubSub\Publisher\Publisher;
use Shavonn\GooglePubSub\Subscriber\StreamingSubscriber;
use Shavonn\GooglePubSub\Subscriber\Subscriber;

class PubSubManager extends Manager
{
    /**
     * The Publisher instance.
     */
    protected ?Publisher $publisher = null;

    /**
     * The array of resolved subscribers.
     */
    protected array $subscribers = [];

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return 'pubsub';
    }

    /**
     * Create the Pub/Sub driver.
     */
    protected function createPubsubDriver(): PubSubClient
    {
        $config = $this->config->get('pubsub', []);

        $pubsubConfig = [
            'projectId' => $config['project_id'] ?? null,
        ];

        // Check for emulator
        if ($emulatorHost = $config['emulator_host'] ?? $_SERVER['PUBSUB_EMULATOR_HOST'] ?? null) {
            $pubsubConfig['emulatorHost'] = $emulatorHost;
        }

        if (empty($pubsubConfig['projectId'])) {
            throw new PubSubException('Google Cloud project ID is required');
        }

        $authMethod = $config['auth_method'] ?? 'application_default';

        if ($authMethod === 'key_file' && ! isset($pubsubConfig['emulatorHost'])) {
            $keyFile = $config['key_file'] ?? null;

            if (empty($keyFile)) {
                throw new PubSubException('Key file path is required when using key_file auth method');
            }

            if (! file_exists($keyFile)) {
                throw new PubSubException("Key file not found: {$keyFile}");
            }

            $pubsubConfig['keyFilePath'] = $keyFile;
        }

        return new PubSubClient($pubsubConfig);
    }

    /**
     * Get the PubSub client instance.
     */
    public function client(): PubSubClient
    {
        return $this->driver();
    }

    /**
     * Get the publisher instance.
     */
    public function publisher(): Publisher
    {
        if (! $this->publisher) {
            $this->publisher = new Publisher(
                $this->client(),
                $this->config->get('pubsub', [])
            );
        }

        return $this->publisher;
    }

    /**
     * Create a subscriber instance.
     */
    public function subscriber(string $subscriptionName, ?string $topic = null): Subscriber
    {
        if (! isset($this->subscribers[$subscriptionName])) {
            $config = $this->config->get('pubsub', []);

            // Use StreamingSubscriber if configured
            if ($config['use_streaming'] ?? true) {
                $this->subscribers[$subscriptionName] = new StreamingSubscriber(
                    $this->client(),
                    $subscriptionName,
                    $topic,
                    $config
                );
            } else {
                $this->subscribers[$subscriptionName] = new Subscriber(
                    $this->client(),
                    $subscriptionName,
                    $topic,
                    $config
                );
            }
        }

        return $this->subscribers[$subscriptionName];
    }

    /**
     * Publish a message to a topic.
     *
     * @param  mixed  $data
     * @return string Message ID
     */
    public function publish(string $topic, $data, array $attributes = [], array $options = []): string
    {
        return $this->publisher()->publish($topic, $data, $attributes, $options);
    }

    /**
     * Subscribe to a topic.
     */
    public function subscribe(string $subscription, ?string $topic = null): Subscriber
    {
        return $this->subscriber($subscription, $topic);
    }

    /**
     * Create a topic if it doesn't exist.
     */
    public function createTopic(string $topicName, array $options = []): void
    {
        $topic = $this->client()->topic($topicName);

        if (! $topic->exists()) {
            $topic->create($options);
        }
    }

    /**
     * Create a subscription if it doesn't exist.
     */
    public function createSubscription(string $subscriptionName, string $topicName, array $options = []): void
    {
        $subscription = $this->client()->subscription($subscriptionName);

        if (! $subscription->exists()) {
            $topic = $this->client()->topic($topicName);
            $topic->subscribe($subscriptionName, $options);
        }
    }

    /**
     * List all topics.
     */
    public function topics(): array
    {
        return iterator_to_array($this->client()->topics());
    }

    /**
     * List all subscriptions.
     */
    public function subscriptions(): array
    {
        return iterator_to_array($this->client()->subscriptions());
    }
}
