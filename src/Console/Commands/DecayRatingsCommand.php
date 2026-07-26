<?php

namespace EloquentWorks\RatingKit\Console\Commands;

use EloquentWorks\RatingKit\RatingKitManager;
use Illuminate\Console\Command;

/**
 * Command to decay ratings based on inactivity.
 */
class DecayRatingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rating-kit:decay {--pool=} {--algorithm=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply configured inactivity decay to eligible ratings';

    /**
     * Handle the command execution.
     *
     * @param RatingKitManager $ratingKit The rating kit manager instance.
     * @return int Returns the exit status code.
     */
    public function handle(RatingKitManager $ratingKit): int
    {
        // Call the decayInactive method on the rating kit manager with optional pool and algorithm parameters.
        $count = $ratingKit->decayInactive(
            $this->option('pool') !== null ? (string) $this->option('pool') : null,
            $this->option('algorithm') !== null ? (string) $this->option('algorithm') : null,
        );

        // Output the number of decayed ratings to the console.
        $this->components->info("Decayed {$count} rating(s).");

        // Return a success exit code.
        return self::SUCCESS;
    }
}
