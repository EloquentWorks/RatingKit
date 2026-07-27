<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\CompetitorInput;
use EloquentWorks\RatingKit\Data\TeamInput;

/**
 * Class FideEloAlgorithm
 *
 * Implements the FIDE Elo rating algorithm.
 */
class FideEloAlgorithm extends EloAlgorithm
{
    /**
     * Get the unique key for this algorithm.
     *
     * @return string The unique key for the FIDE Elo algorithm.
     */
    public function key(): string
    {
        return 'fide';
    }

    /**
     * Calculate the K-factor for the given teams and their ratings.
     *
     * @param  TeamInput  $left  The left team input.
     * @param  TeamInput  $right  The right team input.
     * @param  float  $leftRating  The rating of the left team.
     * @param  float  $rightRating  The rating of the right team.
     * @return float The calculated K-factor.
     */
    protected function kFactor(TeamInput $left, TeamInput $right, float $leftRating, float $rightRating): float
    {
        // Determine the minimum number of games played by any competitor in the left team
        $games = min(array_map(static fn (CompetitorInput $competitor): int => $competitor->rating->games, $left->competitors));

        // Return the K-factor based on the number of games and the left team's rating
        if ($games < 30) {
            return 40.0;
        }

        // Return a K-factor of 20 for ratings below 2400, otherwise return 10
        return $leftRating < 2400.0 ? 20.0 : 10.0;
    }
}
