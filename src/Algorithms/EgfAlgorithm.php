<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\TeamInput;

/**
 * Class EgfAlgorithm
 *
 * Implements the EGF (European Go Federation) rating algorithm.
 */
class EgfAlgorithm extends EloAlgorithm
{
    /**
     * Returns the unique key for this algorithm.
     *
     * @return string The unique key for the EGF algorithm.
     */
    public function key(): string
    {
        return 'egf';
    }

    /**
     * Calculates the K-factor for the given teams and their ratings.
     *
     * @param TeamInput $left The left team input.
     * @param TeamInput $right The right team input.
     * @param float $leftRating The rating of the left team.
     * @param float $rightRating The rating of the right team.
     *
     * @return float The calculated K-factor.
     */
    protected function kFactor(TeamInput $left, TeamInput $right, float $leftRating, float $rightRating): float
    {
        // The K-factor is calculated based on the left team's rating and a configurable constant.
        $con = (float) ($this->options['con'] ?? 116.0);

        // The K-factor decreases exponentially as the left team's rating increases, with a minimum value of 10.0.
        return max(10.0, $con * exp(-$leftRating / 2000.0));
    }
}
