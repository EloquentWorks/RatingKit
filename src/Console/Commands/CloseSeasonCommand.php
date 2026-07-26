<?php

namespace EloquentWorks\RatingKit\Console\Commands;

use EloquentWorks\RatingKit\Models\RatingSeason;
use EloquentWorks\RatingKit\RatingKitManager;
use Illuminate\Console\Command;

/**
 * Command to close a rating season and optionally capture its final leaderboard.
 */
class CloseSeasonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rating-kit:close-season {season : Season ID or slug} {--no-snapshot : Do not capture a final leaderboard}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close a rating season and optionally capture its final leaderboard';

    /**
     * Execute the console command.
     *
     * @param RatingKitManager $ratingKit The rating kit manager instance.
     * @return int Returns the exit status code.
     */
    public function handle(RatingKitManager $ratingKit): int
    {
        // Get the season argument and determine if it's an ID or slug
        $value = (string) $this->argument('season');

        /** @var class-string<RatingSeason> $seasonClass */
        $seasonClass = config('rating-kit.models.season', RatingSeason::class);
        $season = is_numeric($value)
            ? $seasonClass::query()->find((int) $value)
            : $seasonClass::query()->where('slug', $value)->first();

        // If the season is not found, display an error message and return a failure status code
        if ($season === null) {
            $this->components->error("Rating season [{$value}] was not found.");

            return self::FAILURE;
        }

        // Close the season and optionally capture a final leaderboard snapshot
        $ratingKit->closeSeason($season, ! (bool) $this->option('no-snapshot'));
        $this->components->info("Closed rating season [{$season->name}].");

        // Return a success exit code
        return self::SUCCESS;
    }
}
