<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Contracts\RatingAlgorithm;
use EloquentWorks\RatingKit\Data\CompetitorInput;
use EloquentWorks\RatingKit\Data\MatchInput;
use EloquentWorks\RatingKit\Data\RatingBatch;
use EloquentWorks\RatingKit\Data\RatingChange;
use EloquentWorks\RatingKit\Data\RatingState;
use EloquentWorks\RatingKit\Data\TeamInput;
use EloquentWorks\RatingKit\Support\Math;

/**
 * Weng-Lin rating algorithm implementation.
 *
 * This class implements the Weng-Lin rating algorithm, which is used to rate competitors based on their performance in matches.
 * It supports teams and multiple teams in a match, and allows for customization through options such as beta, rating floor, and rating ceiling.
 */
class WengLinAlgorithm implements RatingAlgorithm
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(protected array $options = []) {}

    /**
     * Get the unique key identifying the algorithm.
     *
     * @return string Returns the unique key for the algorithm.
     */
    public function key(): string
    {
        return 'weng_lin';
    }

    /**
     * Indicates whether the algorithm supports teams in a match.
     *
     * @return bool Returns true if the algorithm supports teams, false otherwise.
     */
    public function supportsTeams(): bool
    {
        return true;
    }

    /**
     * Indicates whether the algorithm supports multiple teams in a match.
     *
     * @return bool Returns true if the algorithm supports multiple teams, false otherwise.
     */
    public function supportsMultipleTeams(): bool
    {
        return true;
    }

    /**
     * Rate a match and return the resulting rating changes for each competitor.
     *
     * @param  MatchInput  $match  The match input containing teams and their competitors.
     * @return RatingBatch The batch of rating changes resulting from the match.
     */
    public function rate(MatchInput $match): RatingBatch
    {
        // Retrieve the beta parameter from the options, ensuring it is at least 0.000001 to avoid division by zero or negative values.
        $beta = max(0.000001, (float) ($this->options['beta'] ?? 200.0));
        $ratingDeltas = [];
        $deviationFactors = [];
        $comparisons = [];

        // Iterate over each pair of teams in the match to calculate rating deltas and deviation factors based on their performance.
        foreach ($match->teams as $leftIndex => $left) {
            foreach ($match->teams as $rightIndex => $right) {
                // Skip comparisons where the right team is ranked lower than or equal to the left team to avoid redundant calculations.
                if ($rightIndex <= $leftIndex) {
                    continue;
                }

                // Calculate the mean ratings and variances for both teams, as well as the expected outcome and actual outcome based on their ranks.
                $leftMean = $this->teamRating($left);
                $rightMean = $this->teamRating($right);
                $leftVariance = $this->teamVariance($left);
                $rightVariance = $this->teamVariance($right);
                $c = sqrt(max(0.000001, $leftVariance + $rightVariance + 2.0 * $beta ** 2));
                $expected = Math::normalCdf(($leftMean - $rightMean) / $c);
                $actual = $left->rank === $right->rank ? 0.5 : ($left->rank < $right->rank ? 1.0 : 0.0);
                $error = $actual - $expected;
                $information = max(0.000001, $expected * (1.0 - $expected));

                // Update the rating deltas, deviation factors, and comparison counts for each competitor in both teams based on the calculated
                // error and information.
                foreach ($left->competitors as $competitor) {
                    $variance = $competitor->rating->deviation ** 2;
                    $ratingDeltas[$competitor->key] = ($ratingDeltas[$competitor->key] ?? 0.0) + ($variance / $c) * $error;
                    $deviationFactors[$competitor->key] = ($deviationFactors[$competitor->key] ?? 0.0) + ($variance / ($c ** 2)) * $information;
                    $comparisons[$competitor->key] = ($comparisons[$competitor->key] ?? 0) + 1;
                }

                // Update the rating deltas, deviation factors, and comparison counts for each competitor in the right team based on the
                // calculated error and information.
                foreach ($right->competitors as $competitor) {
                    $variance = $competitor->rating->deviation ** 2;
                    $ratingDeltas[$competitor->key] = ($ratingDeltas[$competitor->key] ?? 0.0) - ($variance / $c) * $error;
                    $deviationFactors[$competitor->key] = ($deviationFactors[$competitor->key] ?? 0.0) + ($variance / ($c ** 2)) * $information;
                    $comparisons[$competitor->key] = ($comparisons[$competitor->key] ?? 0) + 1;
                }
            }
        }

        // Initialize an array to hold the rating changes for each competitor.
        $changes = [];

        // Calculate the new rating state for each competitor in each team based on the accumulated rating deltas and deviation factors.
        foreach ($match->teams as $team) {
            foreach ($team->competitors as $competitor) {
                // Determine the number of comparisons for the competitor, ensuring it is at least 1 to avoid division by zero.
                $count = max(1, $comparisons[$competitor->key] ?? 1);
                $delta = ($ratingDeltas[$competitor->key] ?? 0.0) / $count;
                $shrink = ($deviationFactors[$competitor->key] ?? 0.0) / $count;
                $newDeviation = $competitor->rating->deviation * sqrt(max(0.05, 1.0 - min(0.95, $shrink)));

                // Calculate the new rating state for the competitor after applying the rating change and deviation adjustment.
                $after = new RatingState(
                    rating: Math::clamp(
                        $competitor->rating->rating + $delta,
                        isset($this->options['rating_floor']) ? (float) $this->options['rating_floor'] : null,
                        isset($this->options['rating_ceiling']) ? (float) $this->options['rating_ceiling'] : null,
                    ),
                    deviation: max(25.0, $newDeviation),
                    volatility: $competitor->rating->volatility,
                    games: $competitor->rating->games + 1,
                    provisional: $competitor->rating->provisional,
                    metadata: $competitor->rating->metadata,
                );

                // Store the calculated rating change for the competitor in the changes array.
                $changes[$competitor->key] = new RatingChange($competitor->key, $competitor->rating, $after);
            }
        }

        // Return a new RatingBatch containing the calculated rating changes and the algorithm key.
        return new RatingBatch($changes, ['algorithm' => $this->key()]);
    }

    /**
     * Calculate the weighted average rating of a team based on its competitors' ratings and weights.
     *
     * @param  TeamInput  $team  The team for which to calculate the rating.
     * @return float The calculated weighted average rating of the team.
     */
    protected function teamRating(TeamInput $team): float
    {
        // Calculate the weighted average rating of the team based on the ratings and weights of its competitors.
        $weight = array_sum(array_map(static fn (CompetitorInput $competitor): float => $competitor->weight, $team->competitors));

        // If the team aggregation option is set to 'sum', return the sum of the weighted ratings; otherwise, return the average weighted rating.
        return array_sum(array_map(
            static fn (CompetitorInput $competitor): float => $competitor->rating->rating * $competitor->weight,
            $team->competitors,
        )) / max(0.000001, $weight);
    }

    /**
     * Calculate the variance of a team's ratings based on its competitors' rating deviations and weights.
     *
     * @param  TeamInput  $team  The team for which to calculate the variance.
     * @return float The calculated variance of the team's ratings.
     */
    protected function teamVariance(TeamInput $team): float
    {
        // Calculate the total weight of the team based on the weights of its competitors.
        $weight = array_sum(array_map(static fn (CompetitorInput $competitor): float => $competitor->weight, $team->competitors));

        // Calculate the variance of the team's ratings based on the rating deviations and weights of its competitors.
        $variance = array_sum(array_map(
            static fn (CompetitorInput $competitor): float => ($competitor->rating->deviation * $competitor->weight) ** 2,
            $team->competitors,
        ));

        // If the team aggregation option is set to 'sum', return the sum of the variances; otherwise, return the average variance.
        return ($this->options['team_aggregation'] ?? 'average') === 'sum'
            ? $variance
            : $variance / max(0.000001, $weight ** 2);
    }
}
