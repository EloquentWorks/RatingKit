<?php

namespace EloquentWorks\RatingKit\Algorithms;

/**
 * Class BradleyTerryAlgorithm
 *
 * Implements the Bradley-Terry algorithm for rating players based on pairwise comparisons.
 */
class BradleyTerryAlgorithm extends EloAlgorithm
{
    /**
     * Get the unique key for this algorithm.
     *
     * @return string
     */
    public function key(): string
    {
        return 'bradley_terry';
    }
}
