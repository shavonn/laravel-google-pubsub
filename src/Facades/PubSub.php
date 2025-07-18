<?php

declare(strict_types=1);

namespace SysMatter\GooglePubSub\Facades;

use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Support\Facades\Facade;
use SysMatter\GooglePubSub\Publisher\Publisher;
use SysMatter\GooglePubSub\Subscriber\Subscriber;

/**
 * @method static string publish(string $topic, mixed $data, array $attributes = [], array $options = [])
 * @method static array publishBatch(string $topic, array $messages, array $options = [])
 * @method static Subscriber subscribe(string $subscription, ?string $topic = null)
 * @method static Publisher publisher()
 * @method static Subscriber subscriber(string $subscriptionName, ?string $topic = null)
 * @method static void createTopic(string $topicName, array $options = [])
 * @method static void createSubscription(string $subscriptionName, string $topicName, array $options = [])
 * @method static array topics()
 * @method static array subscriptions()
 * @method static PubSubClient client()
 *
 * @see \SysMatter\GooglePubSub\PubSubManager
 */
class PubSub extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'pubsub';
    }
}
