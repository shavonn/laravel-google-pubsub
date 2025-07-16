<?php

namespace Shavonn\GooglePubSub\Console\Commands;

use Illuminate\Console\Command;
use Shavonn\GooglePubSub\Facades\PubSub;

class ListenCommand extends Command
{
    protected $signature = 'pubsub:listen 
                            {subscription : Subscription name}
                            {--topic= : Topic name (required if subscription doesn\'t exist)}
                            {--max-messages=100 : Maximum messages per pull}';

    protected $description = 'Listen for messages on a Pub/Sub subscription';

    public function handle(): int
    {
        $subscription = $this->argument('subscription');
        $topic = $this->option('topic');

        $this->info("Starting listener for subscription '{$subscription}'...");

        try {
            $subscriber = PubSub::subscribe($subscription, $topic);

            $subscriber->handler(function ($data, $message) {
                $this->info('Received message: ' . $message->id());
                $this->line(json_encode($data, JSON_PRETTY_PRINT));

                if ($attributes = $message->attributes()) {
                    $this->line('Attributes: ' . json_encode($attributes, JSON_PRETTY_PRINT));
                }
            });

            $subscriber->onError(function ($error, $message) {
                $this->error('Error processing message: ' . $error->getMessage());
            });

            $this->info('Listening for messages... Press Ctrl+C to stop.');

            $subscriber->stream([
                'max_messages_per_pull' => (int) $this->option('max-messages'),
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to start listener: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
