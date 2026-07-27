<?php

namespace EloquentWorks\RatingKit\Events;

use EloquentWorks\RatingKit\Models\Rating;
use EloquentWorks\RatingKit\Models\RatingMatch;

/**
 * Class RatingUpdated
 *
 * Event that is fired when a rating is updated.
 */
class RatingUpdated
{
    /**
     * Create a new event instance.
     *
     * @param  Rating  $rating  The rating that was updated
     * @param  RatingMatch  $match  The match associated with the rating update
     * @param  float  $before  The rating value before the update
     * @param  float  $after  The rating value after the update
     */
    public function __construct(
        public Rating $rating,
        public RatingMatch $match,
        public float $before,
        public float $after,
    ) {}
}
