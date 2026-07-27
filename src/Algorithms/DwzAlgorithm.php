<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\CompetitorInput;
use EloquentWorks\RatingKit\Data\TeamInput;

/**
 * Class DwzAlgorithm
 *
 * Implements the DWZ (Deutsche Wertungszahl) rating algorithm, which is a variant of the Elo rating system.
 */
class DwzAlgorithm extends EloAlgorithm
{
    /**
     * Get the unique key for this algorithm.
     *
     * @return string Returns the unique key for this algorithm.
     */
    public function key(): string
    {
        return 'dwz';
    }

    /**
     * Calculate the K-factor for a given match between two teams.
     *
     * @param  TeamInput  $left  The left team input data.
     * @param  TeamInput  $right  The right team input data.
     * @param  float  $leftRating  The rating of the left team.
     * @param  float  $rightRating  The rating of the right team.
     * @return float Returns the calculated K-factor.
     */
    protected function kFactor(TeamInput $left, TeamInput $right, float $leftRating, float $rightRating): float
    {
        // Calculate the K-factor based on the development coefficient and the number of games played by the competitors in the left team.
        $development = (float) ($this->options['development_coefficient'] ?? 30.0);
        $games = max(0, min(array_map(static fn (CompetitorInput $competitor): int => $competitor->rating->games, $left->competitors)));

        // Return the K-factor, ensuring it is at least 5.0, and adjusting based on the number of games played.
        return max(5.0, $development / (1.0 + $games / 30.0));
    }
}
