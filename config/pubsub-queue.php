<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Pub/Sub Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Google Pub/Sub settings for queue operations.
    |
    */

    'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configure authentication method for Google Pub/Sub.
    | Supported: "application_default", "key_file"
    |
    */

    'auth_method' => env('PUBSUB_AUTH_METHOD', 'application_default'),

    'key_file' => env('GOOGLE_APPLICATION_CREDENTIALS'),

    /*
    |--------------------------------------------------------------------------
    | Default Queue Configuration
    |--------------------------------------------------------------------------
    */

    'default_queue' => env('PUBSUB_DEFAULT_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Queue Options
    |--------------------------------------------------------------------------
    |
    | Configure default options for all queues. These can be overridden
    | per queue in your queue.php configuration.
    |
    */

    'queue_options' => [
        // Automatically create topics and subscriptions if they don't exist
        'auto_create_topics' => env('PUBSUB_AUTO_CREATE_TOPICS', true),
        'auto_create_subscriptions' => env('PUBSUB_AUTO_CREATE_SUBSCRIPTIONS', true),

        // Subscription suffix for Laravel consumers
        'subscription_suffix' => env('PUBSUB_SUBSCRIPTION_SUFFIX', '-laravel'),

        // Message acknowledgment deadline (seconds)
        'ack_deadline' => env('PUBSUB_ACK_DEADLINE', 60),

        // Maximum messages to pull per request
        'max_messages' => env('PUBSUB_MAX_MESSAGES', 10),

        // Wait time for messages (seconds)
        'wait_time' => env('PUBSUB_WAIT_TIME', 3),

        // Enable message ordering
        'enable_message_ordering' => env('PUBSUB_ENABLE_ORDERING', false),

        // Retry policy
        'retry_policy' => [
            'minimum_backoff' => env('PUBSUB_MIN_BACKOFF', 10),
            'maximum_backoff' => env('PUBSUB_MAX_BACKOFF', 600),
        ],

        // Dead letter policy
        'dead_letter_policy' => [
            'enabled' => env('PUBSUB_DEAD_LETTER_ENABLED', true),
            'max_delivery_attempts' => env('PUBSUB_MAX_DELIVERY_ATTEMPTS', 5),
            'dead_letter_topic_suffix' => env('PUBSUB_DEAD_LETTER_SUFFIX', '-dead-letter'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Options
    |--------------------------------------------------------------------------
    */

    'message_options' => [
        // Add Laravel-specific attributes to messages
        'add_metadata' => env('PUBSUB_ADD_METADATA', true),

        // Compress large payloads
        'compress_payload' => env('PUBSUB_COMPRESS_PAYLOAD', true),
        'compression_threshold' => env('PUBSUB_COMPRESSION_THRESHOLD', 1024), // bytes
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Logging
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'log_published_messages' => env('PUBSUB_LOG_PUBLISHED', false),
        'log_consumed_messages' => env('PUBSUB_LOG_CONSUMED', false),
        'log_failed_messages' => env('PUBSUB_LOG_FAILED', true),
    ],
];
