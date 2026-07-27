<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\TeamInput;
use EloquentWorks\RatingKit\Support\Math;

/**
 * Class IngoAlgorithm
 *
 * Implements the Ingo rating algorithm for calculating team ratings based on match outcomes.
 */
class IngoAlgorithm extends AbstractPairwiseAlgorithm
{
    /**
     * Get the unique key for this algorithm.
     *
     * @return string The unique key identifying this algorithm.
     */
    public function key(): string
    {
        return 'ingo';
    }

    /**
     * Calculate the expected outcome for a pair of teams based on their ratings.
     *
     * @param  float  $leftRating  The current rating of the left team.
     * @param  float  $rightRating  The current rating of the right team.
     * @param  TeamInput  $left  The input data for the left team.
     * @param  TeamInput  $right  The input data for the right team.
     * @return float The expected outcome of the match (between 0.0 and 1.0).
     */
    protected function expected(float $leftRating, float $rightRating, TeamInput $left, TeamInput $right): float
    {
        return Math::logistic($rightRating - $leftRating, 400.0);
    }

    /**
     * Calculate the rating delta for a pair of teams based on the actual and expected outcomes.
     *
     * @param  TeamInput  $left  The input data for the left team.
     * @param  TeamInput  $right  The input data for the right team.
     * @param  float  $actual  The actual outcome of the match (1.0 for win, 0.0 for loss).
     * @param  float  $expected  The expected outcome of the match (between 0.0 and 1.0).
     * @param  float  $leftRating  The current rating of the left team.
     * @param  float  $rightRating  The current rating of the right team.
     * @return float The calculated rating delta for the left team.
     */
    protected function pairDelta(
        TeamInput $left,
        TeamInput $right,
        float $actual,
        float $expected,
        float $leftRating,
        float $rightRating,
    ): float {
        $coefficient = (float) ($this->options['development_coefficient'] ?? 10.0);

        // The rating delta is calculated as the negative product of the coefficient and the difference between the actual and expected outcomes.
        return -$coefficient * ($actual - $expected);
    }
}
