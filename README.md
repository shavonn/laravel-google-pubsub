# Laravel Google Pub/Sub

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shavonn/laravel-google-pubsub.svg?style=flat-square)](https://packagist.org/packages/shavonn/laravel-google-pubsub)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/shavonn/laravel-google-pubsub/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/shavonn/laravel-google-pubsub/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/shavonn/laravel-google-pubsub/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/shavonn/laravel-google-pubsub/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/shavonn/laravel-google-pubsub.svg?style=flat-square)](https://packagist.org/packages/shavonn/laravel-google-pubsub)

A comprehensive Google Cloud Pub/Sub integration for Laravel that goes beyond basic queue functionality. This package
provides a complete toolkit for building event-driven architectures, microservice communication, and real-time data
pipelines.

## Features

- **Full Laravel Queue Driver** - Seamless integration with Laravel's queue system
- **Publisher/Subscriber Services** - Direct publishing with compression, metadata, and batch support
- **Event Integration** - Bidirectional event flow between Laravel and Pub/Sub
- **Webhook Support** - Handle push subscriptions with built-in security
- **Schema Validation** - JSON Schema validation for message contracts
- **Streaming Support** - Real-time message processing with StreamingPull
- **Multi-Service Architecture** - Built for microservice communication
- **CloudEvents Support** - Industry-standard event formatting with v1.0 compatibility
- **Enterprise Ready** - Dead letter topics, retry policies, monitoring
- **Emulator Support** - Local development with Google Cloud Pub/Sub emulator
- **Laravel Octane Compatible** - Optimized for high-performance applications
- **Comprehensive CLI** - Rich set of Artisan commands for management

## Requirements

* PHP 8.4+
* Laravel 12.0+
* Google Cloud Pub/Sub PHP library

## Installation

Install the package via Composer:

```bash
composer require shavonn/laravel-google-pubsub
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Shavonn\GooglePubSub\PubSubServiceProvider" --tag="config"
```

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
# Basic Configuration
QUEUE_CONNECTION=pubsub
GOOGLE_CLOUD_PROJECT_ID=your-project-id

# Authentication (choose one method)
# Method 1: Service Account Key File
PUBSUB_AUTH_METHOD=key_file
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json

# Method 2: Application Default Credentials
PUBSUB_AUTH_METHOD=application_default

# Optional Settings
PUBSUB_DEFAULT_QUEUE=default
PUBSUB_AUTO_CREATE_TOPICS=true
PUBSUB_AUTO_CREATE_SUBSCRIPTIONS=true
```

### Queue Configuration

Update your `config/queue.php`:

```php
'connections' => [
    'pubsub' => [
        'driver' => 'pubsub',
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'queue' => env('PUBSUB_DEFAULT_QUEUE', 'default'),
        'auth_method' => env('PUBSUB_AUTH_METHOD', 'application_default'),
        'key_file' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        
        // Optional overrides
        'auto_create_topics' => true,
        'auto_create_subscriptions' => true,
        'subscription_suffix' => '-laravel',
        'enable_message_ordering' => false,
    ],
],
```

## Quick Start

### 1. Basic Queue Usage

Use it exactly like any other Laravel queue:

```php
// Dispatch jobs as normal
ProcessPodcast::dispatch($podcast);

// Dispatch to specific queue (Pub/Sub topic)
ProcessPodcast::dispatch($podcast)->onQueue('audio-processing');

// Your Go microservices can subscribe to the same topic
// Subscription name: audio-processing-go-service
```

### 2. Direct Publishing

```php
use Shavonn\GooglePubSub\Facades\PubSub;

// Publish directly to a topic
PubSub::publish('orders', [
    'order_id' => 123,
    'total' => 99.99,
    'customer_id' => 456
]);

// With attributes and ordering
PubSub::publish('orders', $data, [
    'priority' => 'high',
    'source' => 'api'
], [
    'ordering_key' => 'customer-456'
]);
```

### 3. Event Integration

```php
use Shavonn\GooglePubSub\Attributes\PublishTo;
use Shavonn\GooglePubSub\Contracts\ShouldPublishToPubSub;

#[PublishTo('orders')]
class OrderPlaced implements ShouldPublishToPubSub
{
    public function __construct(
        public Order $order
    ) {}
    
    public function pubsubTopic(): string
    {
        return 'orders';
    }
    
    public function toPubSub(): array
    {
        return [
            'order_id' => $this->order->id,
            'total' => $this->order->total,
            'customer_id' => $this->order->customer_id,
        ];
    }
}

// This event automatically publishes to the 'orders' topic
event(new OrderPlaced($order));
```

### 4. Subscribing to Messages

```php
use Shavonn\GooglePubSub\Facades\PubSub;

// Create a subscriber
$subscriber = PubSub::subscribe('orders-processor', 'orders');

// Add message handler
$subscriber->handler(function ($data, $message) {
    // Process the order
    processOrder($data);
});

// Start listening
$subscriber->listen();
```

## Documentation

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md) (comprehensive)
- [Queue Driver](docs/queue-driver.md)
- [Publisher & Subscriber](docs/direct-pubsub.md)
- [Event Integration](docs/event-integration.md)
- [Webhooks (Push Subscriptions)](docs/webhook-push.md)
- [Message Schemas and Validation](docs/message-schemas.md)
- [CloudEvents](docs/cloudevents.md)
- [Artisan Command](docs/artisan-commands.md)
- [Monitoring & Debugging](docs/monitoring-debugging.md)
- [Testing](docs/testing.md)
- [Examples](docs/examples.md)

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email security@example.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
