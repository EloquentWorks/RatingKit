<?php

namespace EloquentWorks\RatingKit\Events;

use EloquentWorks\RatingKit\Models\RatingMatch;

/**
 * Class MatchVoided
 *
 * Event that is dispatched when a rating match is voided.
 */
class MatchVoided
{
    /**
     * Create a new event instance.
     *
     * @param RatingMatch $match The rating match that was voided
     * @param string|null $reason Optional reason for voiding the match
     */
    public function __construct(public RatingMatch $match, public ?string $reason = null) {}
}
