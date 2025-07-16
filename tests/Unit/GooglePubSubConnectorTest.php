<?php

use Shavonn\GooglePubSub\Exceptions\GooglePubSubException;
use Shavonn\GooglePubSub\Queue\GooglePubSubConnector;

it('throws exception when project id is missing', function () {
    $connector = new GooglePubSubConnector();

    $config = [
        'driver' => 'pubsub',
        'queue' => 'default',
    ];

    // Clear the config value to ensure it's not set
    config(['pubsub.project_id' => null]);

    expect(fn () => $connector->connect($config))
        ->toThrow(GooglePubSubException::class, 'Google Cloud project ID is required');
});

it('throws exception when key file is missing for key_file auth', function () {
    $connector = new GooglePubSubConnector();

    $config = [
        'driver' => 'pubsub',
        'project_id' => 'test-project',
        'auth_method' => 'key_file',
        'queue' => 'default',
    ];

    expect(fn () => $connector->connect($config))
        ->toThrow(GooglePubSubException::class, 'Key file path is required when using key_file auth method');
});

it('throws exception when key file does not exist', function () {
    $connector = new GooglePubSubConnector();

    $config = [
        'driver' => 'pubsub',
        'project_id' => 'test-project',
        'auth_method' => 'key_file',
        'key_file' => '/non/existent/file.json',
        'queue' => 'default',
    ];

    expect(fn () => $connector->connect($config))
        ->toThrow(GooglePubSubException::class, 'Key file not found: /non/existent/file.json');
});
