<?php

namespace EloquentWorks\RatingKit\Events;

use EloquentWorks\RatingKit\Models\RatingSeason;

/**
 * Class SeasonClosed
 *
 * Event that is dispatched when a rating season is closed.
 */
class SeasonClosed
{
    /**
     * Create a new event instance.
     *
     * @param \EloquentWorks\RatingKit\Models\RatingSeason $season The rating season that has been closed
     */
    public function __construct(public RatingSeason $season) {}
}
