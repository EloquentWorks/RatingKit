<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\CompetitorInput;
use EloquentWorks\RatingKit\Data\TeamInput;

/**
 * Class UscfAlgorithm
 *
 * Implements the USCF (United States Chess Federation) rating algorithm.
 */
class UscfAlgorithm extends EloAlgorithm
{
    /**
     * Returns the unique key for this algorithm.
     *
     * @return string The unique key for the USCF algorithm.
     */
    public function key(): string
    {
        return 'uscf';
    }

    /**
     * Calculates the K-factor for the given teams and their ratings.
     *
     * @param  TeamInput  $left  The left team input.
     * @param  TeamInput  $right  The right team input.
     * @param  float  $leftRating  The rating of the left team.
     * @param  float  $rightRating  The rating of the right team.
     * @return float The calculated K-factor.
     */
    protected function kFactor(TeamInput $left, TeamInput $right, float $leftRating, float $rightRating): float
    {
        // Determine the number of games played by the competitors in the left team
        $games = max(1, min(array_map(static fn (CompetitorInput $competitor): int => $competitor->rating->games, $left->competitors)));

        // Calculate the K-factor based on the number of games played
        if ($games < 8) {
            return 50.0;
        }

        // For 8 or more games, calculate the K-factor using the formula and clamp it between 16.0 and 32.0
        return max(16.0, min(32.0, 800.0 / ($games + 5.0)));
    }
}
