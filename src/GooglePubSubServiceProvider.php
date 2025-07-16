<?php

namespace Shavonn\GooglePubSub;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Shavonn\GooglePubSub\Failed\GooglePubSubFailedJobProvider;
use Shavonn\GooglePubSub\Queue\GooglePubSubConnector;

class GooglePubSubServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__.'/../config/pubsub.php' => config_path('pubsub.php'),
            ], 'google-pubsub-config');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pubsub.php', 'pubsub');
        $this->registerPubSubConnector();
        $this->registerFailedJobProvider();
    }

    /**
     * Register the Pub/Sub queue connector.
     */
    protected function registerPubSubConnector(): void
    {
        $this->app->resolving('queue', function (QueueManager $manager) {
            $manager->extend('pubsub', function () {
                return new GooglePubSubConnector();
            });
        });
    }

    /**
     * Register the failed job provider for Pub/Sub.
     */
    protected function registerFailedJobProvider(): void
    {
        $this->app->singleton('queue.failed.pubsub', function ($app) {
            return new GooglePubSubFailedJobProvider(
                $app['config']['pubsub']
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'queue.failed.pubsub',
        ];
    }
}
