<?php

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\Subscription;
use Illuminate\Container\Container;
use Shavonn\GooglePubSub\Queue\GooglePubSub;
use Shavonn\GooglePubSub\Queue\Jobs\GooglePubSubJob;

beforeEach(function () {
    $this->container = Mockery::mock(Container::class);
    $this->pubsubQueue = Mockery::mock(GooglePubSub::class);
    $this->message = Mockery::mock(Message::class);
    $this->subscription = Mockery::mock(Subscription::class);
});

it('can get job id from message', function () {
    $this->message->shouldReceive('id')->andReturn('msg-123');

    $job = new GooglePubSubJob(
        $this->container,
        $this->pubsubQueue,
        $this->message,
        $this->subscription,
        'pubsub',
        'default'
    );

    expect($job->getJobId())->toBe('msg-123');
});

it('can get raw body and decompress if needed', function () {
    $originalData = json_encode(['job' => 'TestJob', 'data' => 'test']);
    $compressedData = gzcompress($originalData);

    $this->message->shouldReceive('data')->andReturn($compressedData);
    $this->message->shouldReceive('attributes')->andReturn(['compressed' => 'true']);

    $job = new GooglePubSubJob(
        $this->container,
        $this->pubsubQueue,
        $this->message,
        $this->subscription,
        'pubsub',
        'default'
    );

    expect($job->getRawBody())->toBe($originalData);
});

it('acknowledges message when deleted', function () {
    $this->message->shouldReceive('id')->andReturn('msg-123');
    $this->subscription->shouldReceive('acknowledge')
        ->with($this->message)
        ->once();

    $job = new GooglePubSubJob(
        $this->container,
        $this->pubsubQueue,
        $this->message,
        $this->subscription,
        'pubsub',
        'default'
    );

    $job->delete();
});

it('modifies ack deadline when released', function () {
    $this->message->shouldReceive('id')->andReturn('msg-123');
    $this->subscription->shouldReceive('modifyAckDeadline')
        ->with($this->message, 60)
        ->once();

    $job = new GooglePubSubJob(
        $this->container,
        $this->pubsubQueue,
        $this->message,
        $this->subscription,
        'pubsub',
        'default'
    );

    $job->release(60);
});

it('can get message attributes', function () {
    $attributes = [
        'priority' => 'high',
        'source' => 'api',
    ];

    $this->message->shouldReceive('attributes')->andReturn($attributes);

    $job = new GooglePubSubJob(
        $this->container,
        $this->pubsubQueue,
        $this->message,
        $this->subscription,
        'pubsub',
        'default'
    );

    expect($job->getMessageAttributes())->toBe($attributes);
});

it('can get publish time', function () {
    $publishTime = '2024-01-15T10:30:00Z';
    $this->message->shouldReceive('publishTime')->andReturn($publishTime);

    $job = new GooglePubSubJob(
        $this->container,
        $this->pubsubQueue,
        $this->message,
        $this->subscription,
        'pubsub',
        'default'
    );

    $result = $job->getPublishTime();

    expect($result)->toBeInstanceOf(DateTimeInterface::class)
        ->and($result->format('Y-m-d\TH:i:s\Z'))->toBe($publishTime);
});

it('handles ordering key', function () {
    $this->message->shouldReceive('orderingKey')->andReturn('order-123');

    $job = new GooglePubSubJob(
        $this->container,
        $this->pubsubQueue,
        $this->message,
        $this->subscription,
        'pubsub',
        'default'
    );

    expect($job->hasOrderingKey())->toBeTrue()
        ->and($job->getOrderingKey())->toBe('order-123');
});
