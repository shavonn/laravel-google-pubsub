# Publisher & Subscriber

Direct publisher and subscriber services for building event-driven architectures and real-time data pipelines.

## Publisher Service

The Publisher Service provides direct publishing capabilities with advanced features like compression, metadata
injection, and batch support for high-performance scenarios.

### Basic Publishing

```php
use Shavonn\GooglePubSub\Facades\PubSub;

// Simple message
PubSub::publish('events', ['event' => 'user.registered', 'user_id' => 123]);

// With attributes
PubSub::publish('events', 
    ['event' => 'order.placed', 'order_id' => 456],
    ['priority' => 'high', 'source' => 'web']
);

// With ordering key
PubSub::publish('events',
    ['event' => 'inventory.updated', 'sku' => 'ABC123'],
    ['warehouse' => 'us-east1'],
    ['ordering_key' => 'sku-ABC123']
);
```

### Advanced Publishing Features

**Automatic Compression**: Large payloads are automatically compressed based on configurable thresholds:

```php
// Messages over 1KB are automatically compressed
PubSub::publish('analytics', $largeDataSet); // Auto-compressed with gzip

// Configure compression threshold
// config/pubsub.php
'message_options' => [
    'compress_payload' => true,
    'compression_threshold' => 1024, // bytes
],
```

**Metadata Injection**: Automatic metadata is added for debugging and monitoring:

```php
// Automatically added attributes:
// - published_at: timestamp
// - publisher: 'laravel'  
// - hostname: server hostname
// - app_name: application name

// Configure in config/pubsub.php
'message_options' => [
    'add_metadata' => true,
],
```

### Batch Publishing

For better performance when publishing multiple messages:

```php
$messages = [
    [
        'data' => ['event' => 'page.viewed', 'page' => '/home'],
        'attributes' => ['user_id' => '123']
    ],
    [
        'data' => ['event' => 'page.viewed', 'page' => '/products'],
        'attributes' => ['user_id' => '123']
    ],
    [
        'data' => ['event' => 'button.clicked', 'button' => 'add-to-cart'],
        'attributes' => ['user_id' => '123', 'product_id' => '789']
    ]
];

$messageIds = PubSub::publishBatch('analytics', $messages);
```

### Direct Publisher Access

For more control, access the publisher directly:

```php
$publisher = PubSub::publisher();

// Set a custom formatter
$publisher->setFormatter(new MyCustomFormatter());

// Publish with custom options
$messageId = $publisher->publish('events', $data, $attributes, $options);
```

## Subscriber Service

The Subscriber Service provides both regular and streaming implementations for different use cases, from batch
processing to real-time event handling.

### Regular vs Streaming Subscribers

**Regular Subscriber**: Uses periodic polling with configurable intervals:

```php
$subscriber = PubSub::subscribe('events-processor', 'events');
$subscriber->listen(['wait_time' => 3]); // Polls every 3 seconds when idle
```

**Streaming Subscriber**: Uses StreamingPull for real-time, low-latency delivery:

```php
// Automatically uses StreamingSubscriber when enabled
// config/pubsub.php
'use_streaming' => true,

$subscriber = PubSub::subscribe('events-realtime', 'events');
$subscriber->stream(); // Continuous streaming with instant delivery
```

### Basic Subscription

```php
use Shavonn\GooglePubSub\Facades\PubSub;

// Create a subscriber
$subscriber = PubSub::subscribe('events-processor', 'events');

// Add message handler
$subscriber->handler(function ($data, $message) {
    Log::info('Received event', [
        'data' => $data,
        'id' => $message->id(),
        'attributes' => $message->attributes()
    ]);
    
    // Process the message
    processEvent($data);
});

// Add error handler
$subscriber->onError(function ($exception, $message) {
    Log::error('Failed to process message', [
        'error' => $exception->getMessage(),
        'message_id' => $message?->id()
    ]);
});

// Start listening (blocks until stopped)
$subscriber->listen();
```

### Pull Messages Manually

For more control over message processing:

```php
$subscriber = PubSub::subscribe('events-processor', 'events');

// Pull up to 10 messages
$messages = $subscriber->pull(10);

foreach ($messages as $result) {
    try {
        // Process message
        processEvent($result);
        
        // Manually acknowledge
        $subscriber->acknowledge($message);
    } catch (\Exception $e) {
        // Message will be redelivered
        Log::error('Processing failed', ['error' => $e->getMessage()]);
    }
}
```

### Streaming Subscription

For real-time message processing:

```php
$subscriber = PubSub::subscribe('events-realtime', 'events');

$subscriber->handler(function ($data, $message) {
    // Process immediately as messages arrive
    processRealtimeEvent($data);
});

// Uses StreamingPull for instant delivery
$subscriber->stream([
    'max_messages_per_pull' => 100
]);
```

## Advanced Usage

### Multiple Handlers

Chain multiple handlers for complex processing:

```php
$subscriber = PubSub::subscribe('orders-processor', 'orders')
    ->handler(function ($data, $message) {
        // Validate order
        validateOrder($data);
    })
    ->handler(function ($data, $message) {
        // Process payment
        processPayment($data);
    })
    ->handler(function ($data, $message) {
        // Update inventory
        updateInventory($data);
    })
    ->onError(function ($exception, $message) {
        // Handle any errors
        handleOrderError($exception, $message);
    });
```

### Manual Acknowledgment

Disable auto-acknowledgment for more control:

```php
// In config/pubsub.php
'auto_acknowledge' => false,

// Or per subscriber
$subscriber = PubSub::subscribe('critical-events', 'events');

$subscriber->handler(function ($data, $message) use ($subscriber) {
    try {
        // Process the critical event
        processCriticalEvent($data);
        
        // Only acknowledge on success
        $subscriber->acknowledge($message);
    } catch (\Exception $e) {
        // Extend deadline to retry later
        $subscriber->modifyAckDeadline($message, 60);
        throw $e;
    }
});
```

### Message Filtering

Filter messages based on attributes:

```php
$subscriber->handler(function ($data, $message) {
    $attributes = $message->attributes();
    
    // Only process high priority messages
    if (($attributes['priority'] ?? 'normal') !== 'high') {
        return; // Auto-acknowledges if enabled
    }
    
    processHighPriorityEvent($data);
});
```

## Creating Subscriptions

### Using Artisan Commands

```bash
# Create pull subscription
php artisan pubsub:subscriptions:create analytics-processor analytics

# With options
php artisan pubsub:subscriptions:create orders-processor orders \
    --ack-deadline=120 \
    --enable-ordering \
    --dead-letter
```

### Programmatically

```php
use Shavonn\GooglePubSub\Facades\PubSub;

// Basic subscription
PubSub::createSubscription('my-subscription', 'my-topic');

// With configuration
PubSub::createSubscription('orders-processor', 'orders', [
    'ackDeadlineSeconds' => 120,
    'enableMessageOrdering' => true,
    'deadLetterPolicy' => [
        'deadLetterTopic' => 'projects/my-project/topics/orders-dead-letter',
        'maxDeliveryAttempts' => 5
    ]
]);
```

## Best Practices

### 1. Use Appropriate Acknowledgment Deadlines

Set based on your processing time:

```php
// Fast processing (< 10 seconds)
'ack_deadline' => 30,

// Slow processing (minutes)
'ack_deadline' => 600,
```

### 2. Handle Errors Gracefully

Always set error handlers:

```php
$subscriber->onError(function ($exception, $message) {
    // Log error
    Log::error('Subscription error', [
        'error' => $exception->getMessage(),
        'message_id' => $message?->id()
    ]);
    
    // Alert if critical
    if ($exception instanceof CriticalException) {
        alertOncall($exception);
    }
});
```

### 3. Use Streaming for Real-time

For low-latency requirements:

```php
// Streaming delivers messages immediately
$subscriber->stream();

// Regular pull checks periodically
$subscriber->listen(['wait_time' => 3]);
```

### 4. Monitor Subscription Health

Check subscription metrics:

```bash
# List all subscriptions
php artisan pubsub:subscriptions:list

# Check specific subscription
php artisan pubsub:subscriptions:list --topic=orders
```

### 5. Implement Idempotency

Messages may be delivered more than once:

```php
$subscriber->handler(function ($data, $message) {
    $messageId = $message->id();
    
    // Check if already processed
    if (Cache::has("processed:{$messageId}")) {
        return; // Already processed
    }
    
    // Process message
    processMessage($data);
    
    // Mark as processed
    Cache::put("processed:{$messageId}", true, now()->addHours(24));
});
```
