<?php

namespace EloquentWorks\RatingKit\Events;

use EloquentWorks\RatingKit\Models\Rating;

/**
 * Class RatingAdjusted
 *
 * This event is dispatched whenever a rating is adjusted.
 */
class RatingAdjusted
{
    /**
     * Create a new event instance.
     *
     * @param  Rating  $rating  The rating that was adjusted
     * @param  float  $before  The rating value before the adjustment
     * @param  float  $after  The rating value after the adjustment
     * @param  string  $reason  The reason for the adjustment
     */
    public function __construct(
        public Rating $rating,
        public float $before,
        public float $after,
        public string $reason,
    ) {}
}
