<?php

namespace EloquentWorks\RatingKit;

use EloquentWorks\RatingKit\Console\Commands\CloseSeasonCommand;
use EloquentWorks\RatingKit\Console\Commands\DecayRatingsCommand;
use EloquentWorks\RatingKit\Console\Commands\InstallRatingKitCommand;
use EloquentWorks\RatingKit\Console\Commands\SnapshotLeaderboardCommand;
use EloquentWorks\RatingKit\Support\AlgorithmRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the RatingKit package.
 */
class RatingKitServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        // Merge the package's configuration file with the application's configuration
        $this->mergeConfigFrom(__DIR__.'/../config/rating-kit.php', 'rating-kit');

        // Register the AlgorithmRegistry and RatingKitManager as singletons in the service container
        $this->app->singleton(AlgorithmRegistry::class, fn (Container $app): AlgorithmRegistry => new AlgorithmRegistry($app));
        $this->app->singleton(RatingKitManager::class, fn (Container $app): RatingKitManager => new RatingKitManager(
            $app->make(AlgorithmRegistry::class),
        ));

        // Create an alias for the RatingKitManager in the service container
        $this->app->alias(RatingKitManager::class, 'rating-kit');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // Register the package's migrations
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Register the package's console commands
        $this->commands([
            InstallRatingKitCommand::class,
            DecayRatingsCommand::class,
            SnapshotLeaderboardCommand::class,
            CloseSeasonCommand::class,
        ]);

        // Publish the package's configuration file
        $this->publishes([
            __DIR__.'/../config/rating-kit.php' => config_path('rating-kit.php'),
        ], 'rating-kit-config');

        // Publish the package's migrations
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'rating-kit-migrations');
    }
}
