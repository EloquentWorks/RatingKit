<?php

namespace EloquentWorks\RatingKit\Events;

use EloquentWorks\RatingKit\Models\RatingMatch;

/**
 * Class MatchRated
 *
 * Event that is dispatched when a match has been rated.
 */
class MatchRated
{
    /**
     * Create a new event instance.
     *
     * @param \EloquentWorks\RatingKit\Models\RatingMatch $match The match that has been rated
     */
    public function __construct(public RatingMatch $match) {}
}
