<?php

namespace Shavonn\GooglePubSub\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Shavonn\GooglePubSub\Facades\PubSub;

class CreateSubscriptionCommand extends Command
{
    protected $signature = 'pubsub:subscriptions:create 
                            {name : Subscription name}
                            {topic : Topic name}
                            {--ack-deadline=60 : Acknowledgment deadline in seconds}
                            {--enable-ordering : Enable message ordering}
                            {--dead-letter : Enable dead letter topic}';

    protected $description = 'Create a new Pub/Sub subscription';

    public function handle(): int
    {
        $name = $this->argument('name');
        $topic = $this->argument('topic');

        $this->info("Creating subscription '{$name}' for topic '{$topic}'...");

        try {
            $options = [
                'ackDeadlineSeconds' => (int) $this->option('ack-deadline'),
            ];

            if ($this->option('enable-ordering')) {
                $options['enableMessageOrdering'] = true;
            }

            if ($this->option('dead-letter')) {
                $deadLetterTopic = $topic . '-dead-letter';
                PubSub::createTopic($deadLetterTopic);

                $options['deadLetterPolicy'] = [
                    'deadLetterTopic' => "projects/{$this->getProjectId()}/topics/{$deadLetterTopic}",
                    'maxDeliveryAttempts' => 5,
                ];
            }

            PubSub::createSubscription($name, $topic, $options);

            $this->info("✓ Subscription '{$name}' created successfully!");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to create subscription: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function getProjectId(): string
    {
        return config('pubsub.project_id');
    }
}
