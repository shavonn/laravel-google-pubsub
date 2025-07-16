# Laravel Google Pub/Sub Connector and Queue Support

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shavonn/laravel-google-pubsub.svg?style=flat-square)](https://packagist.org/packages/shavonn/laravel-google-pubsub)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/shavonn/laravel-google-pubsub/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/shavonn/laravel-google-pubsub/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/shavonn/laravel-google-pubsub/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/shavonn/laravel-google-pubsub/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/shavonn/laravel-google-pubsub.svg?style=flat-square)](https://packagist.org/packages/shavonn/laravel-google-pubsub)

A full-featured Google Cloud Pub/Sub queue driver for Laravel that seamlessly integrates with Laravel's queue system
while exposing Pub/Sub's advanced features.

## Features

- **Seamless Laravel Integration**: Works exactly like other Laravel queue drivers
- **Multi-Service Architecture**: Laravel uses one subscription, other services can create their own
- **Flexible Authentication**: Supports both Application Default Credentials and service account key files
- **Message Compression**: Automatically compresses large payloads
- **Message Ordering**: Support for ordered message delivery
- **Auto-provisioning**: Automatically creates topics and subscriptions
- **Dual Retry Mechanisms**: Uses both Laravel and Pub/Sub retry systems
- **Dead Letter Topics**: Failed messages are automatically moved to dead letter topics
- **Monitoring**: Built-in logging for published, consumed, and failed messages
- **Message Attributes**: Add custom metadata to messages

## Requirements

* PHP 8.4+
* Laravel 12.0+
* Google Cloud Pub/Sub PHP library

## Installation

```bash
composer require shavonn/laravel-google-pubsub
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=google-pubsub-config
```

## Configuration

### 1. Add PubSub configuration to config/queue.php:

```php
'connections' => [
    'pubsub' => [
        'driver' => 'pubsub',
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'queue' => env('PUBSUB_DEFAULT_QUEUE', 'default'),
        'auth_method' => env('PUBSUB_AUTH_METHOD', 'application_default'),
        'key_file' => env('GOOGLE_APPLICATION_CREDENTIALS'),

        // Queue-specific overrides (optional)
        'auto_create_topics' => env('PUBSUB_AUTO_CREATE_TOPICS', true),
        'auto_create_subscriptions' => env('PUBSUB_AUTO_CREATE_SUBSCRIPTIONS', true),
        'subscription_suffix' => env('PUBSUB_SUBSCRIPTION_SUFFIX', '-laravel'),
        'ack_deadline' => env('PUBSUB_ACK_DEADLINE', 60),
        'max_messages' => env('PUBSUB_MAX_MESSAGES', 10),
        'enable_message_ordering' => env('PUBSUB_ENABLE_ORDERING', false),
        
        // Only settings above are required or more important, everything else is optional

        // Retry configuration
        'retry_policy' => [
            'minimum_backoff' => env('PUBSUB_MIN_BACKOFF', 10),
            'maximum_backoff' => env('PUBSUB_MAX_BACKOFF', 600),
        ],

        // Dead letter configuration
        'dead_letter_policy' => [
            'enabled' => env('PUBSUB_DEAD_LETTER_ENABLED', true),
            'max_delivery_attempts' => env('PUBSUB_MAX_DELIVERY_ATTEMPTS', 5),
            'dead_letter_topic_suffix' => env('PUBSUB_DEAD_LETTER_SUFFIX', '-dead-letter'),
        ],

        // Message options
        'message_options' => [
            'add_metadata' => env('PUBSUB_ADD_METADATA', true),
            'compress_payload' => env('PUBSUB_COMPRESS_PAYLOAD', true),
            'compression_threshold' => env('PUBSUB_COMPRESSION_THRESHOLD', 1024),
        ],

        // Monitoring
        'monitoring' => [
            'log_published_messages' => env('PUBSUB_LOG_PUBLISHED', false),
            'log_consumed_messages' => env('PUBSUB_LOG_CONSUMED', false),
            'log_failed_messages' => env('PUBSUB_LOG_FAILED', true),
        ],
    ],
],
```

### 2. Update your `.env` file:

```dotenv
QUEUE_CONNECTION=pubsub
GOOGLE_CLOUD_PROJECT_ID=your-project-id

# For service account authentication
PUBSUB_AUTH_METHOD=key_file
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json

# Or use Application Default Credentials
PUBSUB_AUTH_METHOD=application_default
```

#### Full .env config values:

```dotenv
# Queue Connection
QUEUE_CONNECTION=pubsub

# Google Cloud Configuration
GOOGLE_CLOUD_PROJECT_ID=your-project-id
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json

# Pub/Sub Basic Configuration
PUBSUB_DEFAULT_QUEUE=default
PUBSUB_AUTH_METHOD=application_default
# PUBSUB_AUTH_METHOD=key_file

# Queue Options
PUBSUB_AUTO_CREATE_TOPICS=true
PUBSUB_AUTO_CREATE_SUBSCRIPTIONS=true
PUBSUB_SUBSCRIPTION_SUFFIX=-laravel
PUBSUB_ACK_DEADLINE=60
PUBSUB_MAX_MESSAGES=10
PUBSUB_WAIT_TIME=3
PUBSUB_ENABLE_ORDERING=false

# Retry Policy
PUBSUB_MIN_BACKOFF=10
PUBSUB_MAX_BACKOFF=600

# Dead Letter Policy
PUBSUB_DEAD_LETTER_ENABLED=true
PUBSUB_MAX_DELIVERY_ATTEMPTS=5
PUBSUB_DEAD_LETTER_SUFFIX=-dead-letter

# Message Options
PUBSUB_ADD_METADATA=true
PUBSUB_COMPRESS_PAYLOAD=true
PUBSUB_COMPRESSION_THRESHOLD=1024

# Monitoring
PUBSUB_LOG_PUBLISHED=false
PUBSUB_LOG_CONSUMED=false
PUBSUB_LOG_FAILED=true
```

## Usage

### Basic Usage

Use it exactly like any other Laravel queue:

```php
// Dispatch a job to the default queue
ProcessPodcast::dispatch($podcast);

// Dispatch to a specific queue (Pub/Sub topic)
ProcessPodcast::dispatch($podcast)->onQueue('audio-processing');

// Delay a job
ProcessPodcast::dispatch($podcast)->delay(now()->addMinutes(5));
```

### Advanced Features

#### Message Ordering

```php
// Enable message ordering for a specific job
ProcessPodcast::dispatch($podcast)
    ->onQueue('ordered-queue')
    ->through(function ($job) {
        $job->pubsubOptions = [
            'ordering_key' => 'podcast-' . $podcast->id
        ];
    });
```

#### Custom Message Attributes

```php
ProcessPodcast::dispatch($podcast)
    ->through(function ($job) {
        $job->pubsubOptions = [
            'attributes' => [
                'priority' => 'high',
                'source' => 'api',
                'user_id' => auth()->id(),
            ]
        ];
    });
```

#### Accessing Pub/Sub Features in Jobs

```php
class ProcessPodcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Access Pub/Sub message attributes
        if ($this->job instanceof \Shavonn\GooglePubSub\Queue\Jobs\PubSubJob) {
            $attributes = $this->job->getMessageAttributes();
            $publishTime = $this->job->getPublishTime();
            $orderingKey = $this->job->getOrderingKey();
            
            // Access the underlying Pub/Sub message
            $pubsubMessage = $this->job->getPubSubMessage();
        }
        
        // Your job logic here
    }
}
```

### Multiple Service Subscriptions

Laravel will create subscriptions with the configured suffix (default: `-laravel`). Your other services can create their
own subscriptions to the same topics:

```go
// In your Go microservice
subscription := client.Subscription("audio-processing-go-service")
if exists, _ := subscription.Exists(ctx); !exists {
    subscription, _ = client.CreateSubscription(ctx, "audio-processing-go-service", pubsub.SubscriptionConfig{
        Topic: client.Topic("audio-processing"),
    })
}
```

### Queue Worker

Run the queue worker as normal:

```bash
# Process jobs from the default queue
php artisan queue:work pubsub

# Process jobs from a specific queue
php artisan queue:work pubsub --queue=audio-processing

# Process with specific options
php artisan queue:work pubsub --tries=3 --timeout=90
```

### Failed Jobs

Failed jobs are handled through both Laravel's failed jobs table and Pub/Sub's dead letter topics:

```php
// Laravel's built-in failed job handling
php artisan queue:failed

// Dead letter topics are automatically created as: {queue-name}-dead-letter
// You can subscribe to these topics for additional processing
```

## Testing

The package includes comprehensive tests. Run them with:

```bash
composer test
```

## Advanced Configuration

See `config/pubsub.php` for all available options:

- Retry policies
- Dead letter configuration
- Compression settings
- Monitoring options
- Message metadata

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
