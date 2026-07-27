<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\CompetitorInput;
use EloquentWorks\RatingKit\Data\RatingChange;
use EloquentWorks\RatingKit\Data\RatingState;
use EloquentWorks\RatingKit\Support\Math;

/**
 * Implements the Glicko-2 rating algorithm.
 *
 * This class extends the GlickoAlgorithm and provides the implementation for the Glicko-2 rating system.
 * It calculates new ratings, deviations, and volatilities for competitors based on their performance against opponents.
 */
class Glicko2Algorithm extends GlickoAlgorithm
{
    public function key(): string
    {
        return 'glicko2';
    }

    /**
     * @param  list<array{rating: float, deviation: float, score: float}>  $opponents
     */
    protected function rateCompetitor(CompetitorInput $competitor, array $opponents): RatingChange
    {
        // Glicko-2 constants
        $scale = 173.7178;
        $mu = ($competitor->rating->rating - 1500.0) / $scale;
        $phi = max(30.0, $competitor->rating->deviation) / $scale;
        $sigma = max(0.000001, $competitor->rating->volatility);
        $varianceInverse = 0.0;
        $deltaSum = 0.0;

        // Calculate the variance and delta based on opponents' ratings and scores
        foreach ($opponents as $opponent) {
            $opponentMu = ($opponent['rating'] - 1500.0) / $scale;
            $opponentPhi = max(30.0, $opponent['deviation']) / $scale;
            $g = 1.0 / sqrt(1.0 + 3.0 * $opponentPhi ** 2 / (M_PI ** 2));
            $expected = 1.0 / (1.0 + exp(-$g * ($mu - $opponentMu)));
            $varianceInverse += $g * $g * $expected * (1.0 - $expected);
            $deltaSum += $g * ($opponent['score'] - $expected);
        }

        // If the variance inverse is less than or equal to zero, return the current rating without changes
        if ($varianceInverse <= 0.0) {
            return new RatingChange($competitor->key, $competitor->rating, $competitor->rating);
        }

        // Calculate the new variance, delta, and update the rating state
        $variance = 1.0 / $varianceInverse;
        $delta = $variance * $deltaSum;
        $newSigma = $this->newVolatility($phi, $sigma, $delta, $variance);
        $preRatingPhi = sqrt($phi ** 2 + $newSigma ** 2);
        $newPhi = 1.0 / sqrt(1.0 / ($preRatingPhi ** 2) + 1.0 / $variance);
        $newMu = $mu + $newPhi ** 2 * $deltaSum;
        $after = new RatingState(
            rating: Math::clamp(
                1500.0 + $scale * $newMu,
                isset($this->options['rating_floor']) ? (float) $this->options['rating_floor'] : null,
                isset($this->options['rating_ceiling']) ? (float) $this->options['rating_ceiling'] : null,
            ),
            deviation: max(30.0, min(350.0, $scale * $newPhi)),
            volatility: $newSigma,
            games: $competitor->rating->games + 1,
            provisional: $competitor->rating->provisional,
            metadata: $competitor->rating->metadata,
        );

        // Return the rating change with the updated rating state
        return new RatingChange($competitor->key, $competitor->rating, $after);
    }

    /**
     * Calculate the new volatility for a competitor based on their performance.
     *
     * @param  float  $phi  The current deviation of the competitor.
     * @param  float  $sigma  The current volatility of the competitor.
     * @param  float  $delta  The change in rating based on performance against opponents.
     * @param  float  $variance  The variance calculated from opponents' ratings and scores.
     * @return float The new volatility for the competitor.
     */
    protected function newVolatility(float $phi, float $sigma, float $delta, float $variance): float
    {
        // Retrieve the tau and epsilon values from options, ensuring they are above minimum thresholds
        $tau = max(0.000001, (float) ($this->options['tau'] ?? 0.5));
        $epsilon = max(0.000000001, (float) ($this->options['epsilon'] ?? 0.000001));
        $a = log($sigma ** 2);

        // Define the function f(x) used in the iterative process to find the new volatility
        $f = static function (float $x) use ($delta, $phi, $variance, $a, $tau): float {
            $ex = exp($x);
            $numerator = $ex * ($delta ** 2 - $phi ** 2 - $variance - $ex);
            $denominator = 2.0 * ($phi ** 2 + $variance + $ex) ** 2;

            // Return the value of the function f(x) based on the current parameters
            return $numerator / $denominator - ($x - $a) / ($tau ** 2);
        };

        // Initialize the lower and upper bounds for the iterative process to find the new volatility
        $lower = $a;

        // If the delta squared is greater than the sum of phi squared and variance, set the upper bound accordingly
        if ($delta ** 2 > $phi ** 2 + $variance) {
            $upper = log($delta ** 2 - $phi ** 2 - $variance);
        } else {
            $k = 1;
            $upper = $a - $k * $tau;

            // Iteratively adjust the upper bound until f(upper) is non-negative or a maximum of 100 iterations is reached
            while ($f($upper) < 0.0 && $k < 100) {
                $k++;
                $upper = $a - $k * $tau;
            }
        }

        // Calculate the function values at the lower and upper bounds
        $fLower = $f($lower);
        $fUpper = $f($upper);

        // Perform the iterative process to find the new volatility using the Illinois algorithm
        while (abs($upper - $lower) > $epsilon) {
            $denominator = $fUpper - $fLower;

            // If the denominator is too small, break the loop to avoid division by zero
            if (abs($denominator) < 0.000000000001) {
                break;
            }

            // Calculate the candidate value based on the Illinois algorithm and evaluate f(candidate)
            $candidate = $lower + ($lower - $upper) * $fLower / $denominator;
            $fCandidate = $f($candidate);

            // Adjust the lower or upper bounds based on the sign of f(candidate) and f(upper)
            if ($fCandidate * $fUpper <= 0.0) {
                $lower = $upper;
                $fLower = $fUpper;
            } else {
                $fLower /= 2.0;
            }

            // Update the upper bound and its function value to the candidate values for the next iteration
            $upper = $candidate;
            $fUpper = $fCandidate;
        }

        // Return the new volatility calculated from the lower bound
        return exp($lower / 2.0);
    }
}
