<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\TeamInput;

/**
 * Class MarginOfVictoryEloAlgorithm
 *
 * Implements the Elo rating algorithm with adjustments for margin of victory.
 */
class MarginOfVictoryEloAlgorithm extends EloAlgorithm
{
    /**
     * Get the unique key for this algorithm.
     *
     * @return string Returns the unique key for the Margin of Victory Elo algorithm
     */
    public function key(): string
    {
        return 'elo_mov';
    }

    /**
     * Calculate the delta for a pair of teams, taking into account the margin of victory.
     *
     * @param TeamInput $left The left team input
     * @param TeamInput $right The right team input
     * @param float $actual The actual score
     * @param float $expected The expected score
     * @param float $leftRating The rating of the left team
     * @param float $rightRating The rating of the right team
     *
     * @return float Returns the adjusted delta value
     */
    protected function pairDelta(
        TeamInput $left,
        TeamInput $right,
        float $actual,
        float $expected,
        float $leftRating,
        float $rightRating,
    ): float {
        // Calculate the base delta using the parent class's method
        $base = parent::pairDelta($left, $right, $actual, $expected, $leftRating, $rightRating);

        // If either team has a null score, return the base delta without any modifications
        if ($left->score === null || $right->score === null) {
            return $base;
        }

        // Calculate the margin of victory and apply the MOV adjustment
        $margin = abs($left->score - $right->score);
        $exponent = (float) ($this->options['mov_exponent'] ?? 0.8);
        $multiplier = max(1.0, log($margin + 1.0) ** $exponent);
        $ratingCorrection = 2.2 / (($leftRating - $rightRating) * ($actual >= 0.5 ? 1.0 : -1.0) / 1000.0 + 2.2);

        // Return the adjusted delta, ensuring it is within the bounds of 0.25 and 4.0
        return $base * $multiplier * max(0.25, min(4.0, $ratingCorrection));
    }
}
