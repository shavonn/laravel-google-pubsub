<?php

declare(strict_types=1);

namespace SysMatter\GooglePubSub\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use SysMatter\GooglePubSub\Facades\PubSub;

class CreateTopicCommand extends Command
{
    protected $signature = 'pubsub:topics:create 
                            {name : Topic name}
                            {--enable-ordering : Enable message ordering}';

    protected $description = 'Create a new Pub/Sub topic';

    public function handle(): int
    {
        $name = $this->argument('name');

        $this->info("Creating topic '{$name}'...");

        try {
            $options = [];

            if ($this->option('enable-ordering')) {
                $options['enableMessageOrdering'] = true;
            }

            PubSub::createTopic($name, $options);

            $this->info("✓ Topic '{$name}' created successfully!");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to create topic: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
