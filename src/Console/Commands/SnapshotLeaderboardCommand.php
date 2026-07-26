<?php

namespace EloquentWorks\RatingKit\Console\Commands;

use EloquentWorks\RatingKit\RatingKitManager;
use Illuminate\Console\Command;

/**
 * Command to capture a persistent leaderboard snapshot.
 */
class SnapshotLeaderboardCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rating-kit:snapshot
        {--pool= : Rating pool}
        {--algorithm= : Rating algorithm}
        {--season= : Season ID}
        {--limit=100 : Maximum entries to store}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Capture a persistent leaderboard snapshot';

    /**
     * Execute the console command.
     *
     * @param RatingKitManager $ratingKit The rating kit manager instance.
     * @return int Returns the exit status code.
     */
    public function handle(RatingKitManager $ratingKit): int
    {
        // Capture a snapshot of the leaderboard based on the provided options
        $snapshot = $ratingKit->snapshotLeaderboard(
            $this->option('pool') !== null ? (string) $this->option('pool') : null,
            $this->option('algorithm') !== null ? (string) $this->option('algorithm') : null,
            $this->option('season') !== null ? (int) $this->option('season') : null,
            max(1, (int) $this->option('limit')),
        );

        // Output the snapshot details to the console
        $this->components->info("Created leaderboard snapshot #{$snapshot->getKey()} with {$snapshot->entry_count} entries.");

        // Return a success exit code
        return self::SUCCESS;
    }
}
