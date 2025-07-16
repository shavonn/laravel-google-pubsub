<?php

namespace Shavonn\GooglePubSub\Queue;

use Exception;
use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Support\Arr;
use Shavonn\GooglePubSub\Exceptions\GooglePubSubException;

class GooglePubSubConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     *
     * @throws GooglePubSubException
     */
    public function connect(array $config): Queue
    {
        try {
            $pubsubConfig = $this->getPubSubConfig($config);
        } catch (GooglePubSubException $e) {
            throw $e;
        }

        try {
            $client = new PubSubClient($pubsubConfig);
        } catch (Exception $e) {
            throw new GooglePubSubException(
                "Failed to create Pub/Sub client: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }

        return new GooglePubSubQueue(
            $client,
            $config['queue'] ?? config('pubsub.default_queue'),
            Arr::except($config, ['driver', 'project_id', 'key_file'])
        );
    }

    /**
     * Get the Pub/Sub client configuration.
     *
     * @throws GooglePubSubException
     */
    protected function getPubSubConfig(array $config): array
    {
        $projectId = $config['project_id'] ?? config('pubsub.project_id');

        if (empty($projectId)) {
            throw new GooglePubSubException('Google Cloud project ID is required');
        }

        $pubsubConfig = compact('projectId');

        $authMethod = $config['auth_method'] ?? config('pubsub.auth_method', 'application_default');

        if ($authMethod === 'key_file') {
            $keyFile = $config['key_file'] ?? config('pubsub.key_file');

            if (empty($keyFile)) {
                throw new GooglePubSubException('Key file path is required when using key_file auth method');
            }

            if (! file_exists($keyFile)) {
                throw new GooglePubSubException("Key file not found: {$keyFile}");
            }

            $pubsubConfig['keyFilePath'] = $keyFile;
        }

        return $pubsubConfig;
    }
}
