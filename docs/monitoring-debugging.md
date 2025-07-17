# Monitoring & Debugging

Comprehensive monitoring and debugging tools to ensure your Pub/Sub integration runs smoothly in production.

## Built-in Logging

### Configuration

Control logging verbosity in `config/pubsub.php`:

```php
'monitoring' => [
    'log_published_messages' => env('PUBSUB_LOG_PUBLISHED', false),
    'log_consumed_messages' => env('PUBSUB_LOG_CONSUMED', false),
    'log_failed_messages' => env('PUBSUB_LOG_FAILED', true),
    'log_webhooks' => env('PUBSUB_LOG_WEBHOOKS', false),
],
```

### Log Examples

Published messages:

```
[2024-01-15 10:30:45] production.INFO: Published message to Pub/Sub {
    "topic": "orders",
    "message_id": "1234567890",
    "attributes": {"priority": "high"},
    "size": 1024
}
```

Consumed messages:

```
[2024-01-15 10:30:46] production.INFO: Processing Pub/Sub message {
    "subscription": "orders-laravel",
    "message_id": "1234567890",
    "publish_time": "2024-01-15T10:30:45Z",
    "attributes": {"priority": "high"}
}
```

Failed messages:

```
[2024-01-15 10:30:47] production.ERROR: Failed to process Pub/Sub message {
    "error": "

