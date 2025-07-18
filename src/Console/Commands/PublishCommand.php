<?php

declare(strict_types=1);

namespace SysMatter\GooglePubSub\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use SysMatter\GooglePubSub\Facades\PubSub;

class PublishCommand extends Command
{
    protected $signature = 'pubsub:publish 
                            {topic : Topic name}
                            {message : Message data (JSON)}
                            {--attributes=* : Message attributes in key:value format}
                            {--ordering-key= : Message ordering key}';

    protected $description = 'Publish a message to a Pub/Sub topic';

    public function handle(): int
    {
        $topic = $this->argument('topic');
        $messageData = $this->argument('message');

        try {
            // Parse message data
            $data = json_decode($messageData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = $messageData; // Use as string if not JSON
            }

            // Parse attributes
            $attributes = [];
            foreach ($this->option('attributes') as $attr) {
                [$key, $value] = explode(':', $attr, 2);
                $attributes[$key] = $value;
            }

            // Prepare options
            $options = [];
            if ($orderingKey = $this->option('ordering-key')) {
                $options['ordering_key'] = $orderingKey;
            }

            $this->info("Publishing message to topic '{$topic}'...");

            $messageId = PubSub::publish($topic, $data, $attributes, $options);

            $this->info("✓ Message published successfully!");
            $this->line("Message ID: {$messageId}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to publish message: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
