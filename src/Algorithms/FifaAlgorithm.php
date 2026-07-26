<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\TeamInput;

/**
 * Class FifaAlgorithm
 *
 * Implements the FIFA rating algorithm, extending the Elo rating system.
 */
class FifaAlgorithm extends EloAlgorithm
{
    /**
     * Get the unique key for this algorithm.
     *
     * @return string The unique key for the FIFA algorithm.
     */
    public function key(): string
    {
        return 'fifa';
    }

    /**
     * Calculate the K-factor for the FIFA algorithm.
     *
     * @param TeamInput $left The left team input.
     * @param TeamInput $right The right team input.
     * @param float $leftRating The rating of the left team.
     * @param float $rightRating The rating of the right team.
     * @return float The K-factor for the FIFA algorithm.
     */
    protected function kFactor(TeamInput $left, TeamInput $right, float $leftRating, float $rightRating): float
    {
        return (float) ($this->options['importance'] ?? 25.0);
    }
}
