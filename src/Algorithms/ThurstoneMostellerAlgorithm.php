<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\TeamInput;
use EloquentWorks\RatingKit\Support\Math;

/**
 * Class ThurstoneMostellerAlgorithm
 *
 * Implements the Thurstone-Mosteller algorithm for pairwise comparisons.
 */
class ThurstoneMostellerAlgorithm extends AbstractPairwiseAlgorithm
{
    /**
     * Returns the unique key for this algorithm.
     *
     * @return string The unique key for the Thurstone-Mosteller algorithm.
     */
    public function key(): string
    {
        return 'thurstone_mosteller';
    }

    /**
     * Returns the name of this algorithm.
     *
     * @return string The name of the Thurstone-Mosteller algorithm.
     */
    protected function expected(float $leftRating, float $rightRating, TeamInput $left, TeamInput $right): float
    {
        // The beta parameter controls the spread of the normal distribution used in the calculation.
        $beta = max(0.000001, (float) ($this->options['beta'] ?? 200.0));

        // The expected score is calculated using the cumulative distribution function (CDF) of the normal distribution.
        return Math::normalCdf(($leftRating - $rightRating) / (sqrt(2.0) * $beta));
    }
}
