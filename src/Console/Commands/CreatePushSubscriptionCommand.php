<?php

declare(strict_types=1);

namespace Shavonn\GooglePubSub\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Shavonn\GooglePubSub\Facades\PubSub;

class CreatePushSubscriptionCommand extends Command
{
    protected $signature = 'pubsub:subscriptions:create-push 
                            {name : Subscription name}
                            {topic : Topic name}
                            {endpoint : Push endpoint URL}
                            {--token= : Authentication token}
                            {--ack-deadline=60 : Acknowledgment deadline in seconds}
                            {--enable-ordering : Enable message ordering}
                            {--dead-letter : Enable dead letter topic}';

    protected $description = 'Create a new Pub/Sub push subscription';

    public function handle(): int
    {
        $name = $this->argument('name');
        $topic = $this->argument('topic');
        $endpoint = $this->argument('endpoint');
        $token = $this->option('token');

        $this->info("Creating push subscription '{$name}' for topic '{$topic}'...");

        try {
            $options = [
                'ackDeadlineSeconds' => (int) $this->option('ack-deadline'),
                'pushConfig' => [
                    'pushEndpoint' => $endpoint,
                ],
            ];

            // Add auth token if provided
            if ($token) {
                $options['pushConfig']['attributes'] = [
                    'x-goog-subscription-authorization' => "Bearer {$token}",
                ];
            }

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

            $this->info("✓ Push subscription '{$name}' created successfully!");
            $this->line("Endpoint: {$endpoint}");

            if ($token) {
                $this->line("Authentication: Bearer token configured");
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to create push subscription: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function getProjectId(): string
    {
        return config('pubsub.project_id');
    }
}
