<?php

namespace EloquentWorks\RatingKit\Console\Commands;

use Illuminate\Console\Command;

/**
 * Class InstallRatingKitCommand
 *
 * This command publishes the configuration and migration files for Laravel RatingKit.
 */
class InstallRatingKitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rating-kit:install {--migrate : Run database migrations after publishing resources}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the Laravel RatingKit configuration and migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Publish the configuration and migration files for Laravel RatingKit
        $this->call('vendor:publish', ['--tag' => 'rating-kit-config']);
        $this->call('vendor:publish', ['--tag' => 'rating-kit-migrations']);

        // Run database migrations if the --migrate option is provided
        if ($this->option('migrate')) {
            $this->call('migrate');
        }

        // Inform the user that Laravel RatingKit has been installed
        $this->components->info('Laravel RatingKit has been installed.');

        // Return a success exit code
        return self::SUCCESS;
    }
}
