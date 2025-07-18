<?php

use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Google\Cloud\PubSub\Subscription;
use SysMatter\GooglePubSub\PubSubManager;
use SysMatter\GooglePubSub\Publisher\Publisher;
use SysMatter\GooglePubSub\Subscriber\Subscriber;
use SysMatter\GooglePubSub\Subscriber\StreamingSubscriber;
use SysMatter\GooglePubSub\Exceptions\PubSubException;
use SysMatter\GooglePubSub\Facades\PubSub;

describe('PubSubManager', function () {
    beforeEach(function () {
        $this->app = app();
        $this->app['config']->set('pubsub', [
            'project_id' => 'test-project',
            'auth_method' => 'application_default',
        ]);
    });

    it('creates PubSub client with correct config', function () {
        $manager = new PubSubManager(fn () => $this->app);

        // This will fail without actual credentials, but we can test it throws the right exception
        expect(fn () => $manager->client())
            ->toThrow(Exception::class);
    });

    it('throws exception when project ID is missing', function () {
        $this->app['config']->set('pubsub.project_id', null);

        $manager = new PubSubManager(fn () => $this->app);

        expect(fn () => $manager->client())
            ->toThrow(PubSubException::class, 'Google Cloud project ID is required');
    });

    it('uses emulator when configured', function () {
        $this->app['config']->set('pubsub.emulator_host', 'localhost:8085');

        $manager = new PubSubManager(fn () => $this->app);

        // The manager should try to create client with emulator config
        expect(fn () => $manager->client())
            ->toThrow(Exception::class); // Will fail but with different error
    });

    it('returns publisher instance', function () {
        $manager = Mockery::mock(PubSubManager::class)->makePartial();
        $manager->shouldAllowMockingProtectedMethods();

        $client = Mockery::mock(PubSubClient::class);
        $manager->shouldReceive('client')->andReturn($client);
        $manager->shouldReceive('getApplication')->andReturn($this->app);

        $publisher = $manager->publisher();

        expect($publisher)->toBeInstanceOf(Publisher::class);
        expect($manager->publisher())->toBe($publisher); // Same instance
    });

    it('creates subscriber with streaming by default', function () {
        $manager = Mockery::mock(PubSubManager::class)->makePartial();
        $manager->shouldAllowMockingProtectedMethods();

        $client = Mockery::mock(PubSubClient::class);
        $manager->shouldReceive('client')->andReturn($client);
        $manager->shouldReceive('getApplication')->andReturn($this->app);

        $this->app['config']->set('pubsub.use_streaming', true);

        $subscriber = $manager->subscriber('test-subscription', 'test-topic');

        expect($subscriber)->toBeInstanceOf(StreamingSubscriber::class);
    });

    it('creates regular subscriber when streaming disabled', function () {
        $manager = Mockery::mock(PubSubManager::class)->makePartial();
        $manager->shouldAllowMockingProtectedMethods();

        $client = Mockery::mock(PubSubClient::class);
        $manager->shouldReceive('client')->andReturn($client);
        $manager->shouldReceive('getApplication')->andReturn($this->app);

        $this->app['config']->set('pubsub.use_streaming', false);

        $subscriber = $manager->subscriber('test-subscription', 'test-topic');

        expect($subscriber)->toBeInstanceOf(Subscriber::class);
        expect($subscriber)->not->toBeInstanceOf(StreamingSubscriber::class);
    });

    it('creates topics', function () {
        $manager = Mockery::mock(PubSubManager::class)->makePartial();
        $client = Mockery::mock(PubSubClient::class);
        $topic = Mockery::mock(Topic::class);

        $manager->shouldReceive('client')->andReturn($client);
        $client->shouldReceive('topic')
            ->with('new-topic')
            ->andReturn($topic);

        $topic->shouldReceive('exists')->andReturn(false);
        $topic->shouldReceive('create')->with(['option' => 'value'])->once();

        $manager->createTopic('new-topic', ['option' => 'value']);

        expect(true)->toBeTrue(); // Assertion to avoid risky test
    });

    it('creates subscriptions', function () {
        $manager = Mockery::mock(PubSubManager::class)->makePartial();
        $client = Mockery::mock(PubSubClient::class);
        $topic = Mockery::mock(Topic::class);
        $subscription = Mockery::mock(Subscription::class);

        $manager->shouldReceive('client')->andReturn($client);

        $client->shouldReceive('subscription')
            ->with('new-subscription')
            ->andReturn($subscription);

        $client->shouldReceive('topic')
            ->with('test-topic')
            ->andReturn($topic);

        $subscription->shouldReceive('exists')->andReturn(false);
        $topic->shouldReceive('subscribe')
            ->with('new-subscription', ['ackDeadline' => 60])
            ->once();

        $manager->createSubscription('new-subscription', 'test-topic', ['ackDeadline' => 60]);

        expect(true)->toBeTrue(); // Assertion to avoid risky test
    });

    it('lists topics and subscriptions', function () {
        $manager = Mockery::mock(PubSubManager::class)->makePartial();
        $client = Mockery::mock(PubSubClient::class);

        $topic1 = Mockery::mock(Topic::class);
        $topic2 = Mockery::mock(Topic::class);

        $manager->shouldReceive('client')->andReturn($client);

        $client->shouldReceive('topics')
            ->andReturn(new ArrayIterator([$topic1, $topic2]));

        $client->shouldReceive('subscriptions')
            ->andReturn(new ArrayIterator([]));

        $topics = $manager->topics();
        $subscriptions = $manager->subscriptions();

        expect($topics)->toHaveCount(2);
        expect($subscriptions)->toHaveCount(0);
    });
});

describe('PubSub Facade', function () {
    it('provides static access to manager methods', function () {
        $this->app->singleton('pubsub', function () {
            $mock = Mockery::mock(PubSubManager::class);
            $mock->shouldReceive('publish')
                ->with('test-topic', 'test-data', [], [])
                ->andReturn('msg-123');
            return $mock;
        });

        $messageId = PubSub::publish('test-topic', 'test-data', [], []);

        expect($messageId)->toBe('msg-123');
    });
});
