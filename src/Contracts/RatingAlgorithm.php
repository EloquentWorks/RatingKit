<?php

namespace EloquentWorks\RatingKit\Contracts;

use EloquentWorks\RatingKit\Data\MatchInput;
use EloquentWorks\RatingKit\Data\RatingBatch;

/**
 * Interface RatingAlgorithm
 *
 * Represents a rating algorithm that can be used to rate matches and update player ratings.
 */
interface RatingAlgorithm
{
    /**
     * Get the unique key for this rating algorithm.
     *
     * @return string The unique key representing this rating algorithm
     */
    public function key(): string;

    /**
     * Determine if this rating algorithm supports teams.
     *
     * @return bool Returns true if the algorithm supports teams, false otherwise
     */
    public function supportsTeams(): bool;

    /**
     * Determine if this rating algorithm supports multiple teams.
     *
     * @return bool Returns true if the algorithm supports multiple teams, false otherwise
     */
    public function supportsMultipleTeams(): bool;

    /**
     * Rate a match and update player ratings based on the match input.
     *
     * @param  MatchInput  $match  The match input containing information about the match
     * @return RatingBatch Returns a batch of updated player ratings after processing the match
     */
    public function rate(MatchInput $match): RatingBatch;
}
