<?php

declare(strict_types=1);

namespace Shavonn\GooglePubSub;

use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\QueueManager;
use Illuminate\Container\Container;
use Shavonn\GooglePubSub\Queue\PubSubConnector;
use Shavonn\GooglePubSub\Failed\PubSubFailedJobProvider;
use Shavonn\GooglePubSub\Events\PubSubEventDispatcher;
use Shavonn\GooglePubSub\Events\PubSubEventSubscriber;

class PubSubServiceProvider extends ServiceProvider
{
    /**
     * The console commands.
     */
    protected array $commands = [
        Console\Commands\ListTopicsCommand::class,
        Console\Commands\CreateTopicCommand::class,
        Console\Commands\ListSubscriptionsCommand::class,
        Console\Commands\CreateSubscriptionCommand::class,
        Console\Commands\ListenCommand::class,
        Console\Commands\PublishCommand::class,
        Console\Commands\ValidateSchemaCommand::class,
    ];

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/pubsub.php' => config_path('pubsub.php')], 'pubsub-config');

            $this->commands($this->commands);
        }

        $this->registerEventIntegration();
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/pubsub.php', 'pubsub');

        $this->registerPubSubManager();
        $this->registerPubSubConnector();
        $this->registerFailedJobProvider();
        $this->registerEventServices();
    }

    /**
     * Register the Pub/Sub manager.
     */
    protected function registerPubSubManager(): void
    {
        $this->app->singleton('pubsub', function () {
            return new PubSubManager(fn () => Container::getInstance());
        });

        $this->app->alias('pubsub', PubSubManager::class);
    }

    /**
     * Register the Pub/Sub queue connector.
     */
    protected function registerPubSubConnector(): void
    {
        $this->app->resolving('queue', function (QueueManager $manager) {
            $manager->extend('pubsub', function () {
                return new PubSubConnector();
            });
        });
    }

    /**
     * Register the failed job provider for Pub/Sub.
     */
    protected function registerFailedJobProvider(): void
    {
        $this->app->singleton('queue.failed.pubsub', function ($app) {
            return new PubSubFailedJobProvider(
                $app['config']['pubsub']
            );
        });
    }

    /**
     * Register event integration services.
     */
    protected function registerEventServices(): void
    {
        $this->app->singleton(PubSubEventDispatcher::class, function ($app) {
            return new PubSubEventDispatcher(
                $app['pubsub'],
                $app['events'],
                $app['config']['pubsub']
            );
        });

        $this->app->singleton(PubSubEventSubscriber::class, function ($app) {
            return new PubSubEventSubscriber(
                $app['pubsub'],
                $app['events'],
                $app['config']['pubsub']
            );
        });
    }

    /**
     * Register event integration.
     */
    protected function registerEventIntegration(): void
    {
        if (config('pubsub.events.enabled', false)) {
            // Register event dispatcher
            $this->app->make(PubSubEventDispatcher::class)->register();

            // Start event subscriber for configured topics
            if (config('pubsub.events.subscribe', true)) {
                $this->app->make(PubSubEventSubscriber::class)->subscribeToConfiguredTopics();
            }
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'pubsub',
            'queue.failed.pubsub',
            PubSubEventDispatcher::class,
            PubSubEventSubscriber::class,
        ];
    }
}
