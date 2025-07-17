<?php

declare(strict_types=1);

namespace Shavonn\GooglePubSub\Subscriber;

use Exception;
use Google\Cloud\PubSub\Message;
use Shavonn\GooglePubSub\Exceptions\SubscriptionException;
use Illuminate\Support\Facades\Log;

class StreamingSubscriber extends Subscriber
{
    /**
     * Use streaming pull to continuously receive messages.
     *
     * @param array $options
     */
    public function stream(array $options = []): void
    {
        try {
            $subscription = $this->getSubscription();

            $pullOptions = [
                'returnImmediately' => false,
                'maxMessages' => $options['max_messages_per_pull'] ?? $this->config['max_messages_per_pull'] ?? 100,
            ];

            Log::info("Started streaming pull on subscription: {$this->subscriptionName}");

            while (true) {
                // Pull messages
                $messages = $subscription->pull($pullOptions);

                if (!empty($messages)) {
                    foreach ($messages as $message) {
                        try {
                            $this->processStreamMessage($message, $subscription);
                        } catch (Exception $e) {
                            $this->handleError($e, $message);

                            // Optionally nack the message by setting ack deadline to 0
                            if ($this->config['nack_on_error'] ?? true) {
                                $subscription->modifyAckDeadline($message, 0);
                            }
                        }
                    }
                }

                // Check for stop signal
                if ($this->shouldStop()) {
                    break;
                }

                // Small delay to prevent tight loop when no messages
                if (empty($messages)) {
                    usleep(100000); // 100ms
                }
            }
        } catch (Exception $e) {
            throw new SubscriptionException(
                "Streaming pull failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Listen using streaming pull (alias for stream).
     */
    public function listen(array $options = []): void
    {
        $this->stream($options);
    }

    /**
     * Process a message from the stream.
     */
    protected function processStreamMessage(Message $message, $subscription): void
    {
        $data = $this->decodeMessage($message);

        // Log if configured
        if ($this->config['monitoring']['log_consumed_messages'] ?? false) {
            Log::debug('Processing streamed message', [
                'subscription' => $this->subscriptionName,
                'message_id' => $message->id(),
                'publish_time' => $message->publishTime(),
            ]);
        }

        // Call all handlers
        foreach ($this->handlers as $handler) {
            $handler($data, $message);
        }

        // Acknowledge the message
        if ($this->config['auto_acknowledge'] ?? true) {
            $subscription->acknowledge($message);
        }
    }

    /**
     * Configure flow control for pulling.
     */
    public function withFlowControl(int $maxMessages): self
    {
        $this->config['max_messages_per_pull'] = $maxMessages;

        return $this;
    }

    /**
     * Set the wait time between pulls when no messages.
     */
    public function withWaitTime(int $milliseconds): self
    {
        $this->config['empty_wait_time'] = $milliseconds;

        return $this;
    }
}
