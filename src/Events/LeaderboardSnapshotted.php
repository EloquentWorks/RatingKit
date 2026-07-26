<?php

namespace EloquentWorks\RatingKit\Events;

use EloquentWorks\RatingKit\Models\LeaderboardSnapshot;

/**
 * Class LeaderboardSnapshotted
 *
 * This event is dispatched when a leaderboard snapshot is created.
 */
class LeaderboardSnapshotted
{
    /**
     * Create a new event instance.
     *
     * @param LeaderboardSnapshot $snapshot The leaderboard snapshot that was created
     */
    public function __construct(public LeaderboardSnapshot $snapshot) {}
}
