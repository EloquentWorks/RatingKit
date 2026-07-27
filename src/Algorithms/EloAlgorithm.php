<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\TeamInput;
use EloquentWorks\RatingKit\Support\Math;

/**
 * Class EloAlgorithm
 *
 * Implements the Elo rating algorithm for pairwise comparisons.
 */
class EloAlgorithm extends AbstractPairwiseAlgorithm
{
    /**
     * Get the unique key for this algorithm.
     *
     * @return string The unique key for the Elo algorithm.
     */
    public function key(): string
    {
        return 'elo';
    }

    /**
     * Calculate the expected score for the left team based on their rating and the right team's rating.
     *
     * @param  float  $leftRating  The rating of the left team.
     * @param  float  $rightRating  The rating of the right team.
     * @param  TeamInput  $left  The input data for the left team.
     * @param  TeamInput  $right  The input data for the right team.
     * @return float The expected score for the left team (between 0 and 1).
     */
    protected function expected(float $leftRating, float $rightRating, TeamInput $left, TeamInput $right): float
    {
        return Math::logistic($leftRating - $rightRating, (float) ($this->options['scale'] ?? 400.0));
    }
}
