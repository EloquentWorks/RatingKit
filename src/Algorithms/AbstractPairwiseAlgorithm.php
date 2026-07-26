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
 * Abstract base class for pairwise rating algorithms.
 *
 * This class provides a framework for implementing rating algorithms that operate on pairwise comparisons
 * between teams or competitors. It handles the overall structure of the rating process, including team rating
 * aggregation, distribution factors, and rating updates.
 */
abstract class AbstractPairwiseAlgorithm implements RatingAlgorithm
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(protected array $options = []) {}

    /**
     * Determine if the algorithm supports team-based matches.
     *
     * @return bool Returns true if the algorithm supports teams, false otherwise.
     */
    public function supportsTeams(): bool
    {
        return true;
    }
    
    /**
     * Determine if the algorithm supports matches with multiple teams.
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
     * @param  MatchInput  $match  The match input data containing teams and their competitors.
     * @return RatingBatch The batch of rating changes resulting from the match.
     */
    public function rate(MatchInput $match): RatingBatch
    {
        // Initialize arrays to store the cumulative rating deltas and comparison counts for each team.
        $teamDeltas = array_fill(0, count($match->teams), 0.0);
        $comparisons = array_fill(0, count($match->teams), 0);

        // Iterate over each pair of teams in the match to calculate rating deltas based on their actual and expected outcomes.
        foreach ($match->teams as $leftIndex => $left) {
            foreach ($match->teams as $rightIndex => $right) {
                if ($rightIndex <= $leftIndex) {
                    continue;
                }

                // Calculate the overall ratings for the left and right teams based on their competitors and weights.
                $leftRating = $this->teamRating($left);
                $rightRating = $this->teamRating($right);
                $actual = $left->rank === $right->rank ? 0.5 : ($left->rank < $right->rank ? 1.0 : 0.0);
                $expected = $this->expected($leftRating, $rightRating, $left, $right);
                $delta = $this->pairDelta($left, $right, $actual, $expected, $leftRating, $rightRating);

                // Update the cumulative rating deltas and comparison counts for the left and right teams based on the calculated delta.
                $teamDeltas[$leftIndex] += $delta;
                $teamDeltas[$rightIndex] -= $delta;
                $comparisons[$leftIndex]++;
                $comparisons[$rightIndex]++;
            }
        }

        // Prepare an array to store the rating changes for each competitor after processing all team comparisons.
        $changes = [];

        // Iterate over each team and its competitors to calculate the final rating changes based on the cumulative deltas
        // and distribution factors.
        foreach ($match->teams as $teamIndex => $team) {
            $teamDelta = $comparisons[$teamIndex] > 0
                ? $teamDeltas[$teamIndex] / $comparisons[$teamIndex]
                : 0.0;
            
            // Iterate over each competitor in the team to calculate their individual rating changes based on the
            // team's delta and their participation factor.
            foreach ($team->competitors as $competitor) {
                $participationFactor = $this->distributionFactor($team, $competitor);
                $delta = $teamDelta * $participationFactor;
                $after = $this->nextState($competitor, $delta);

                // Store the rating change for the competitor, including their key, previous rating, new rating, and relevant metadata.
                $changes[$competitor->key] = new RatingChange(
                    key: $competitor->key,
                    before: $competitor->rating,
                    after: $after,
                    metadata: [
                        'team_delta' => $teamDelta,
                        'participation_factor' => $participationFactor,
                    ],
                );
            }
        }

        // Return a RatingBatch containing all the rating changes for the competitors, along with the algorithm key.
        return new RatingBatch($changes, ['algorithm' => $this->key()]);
    }

    /**
     * Calculate the overall rating for a team based on its competitors and their weights.
     *
     * @param  TeamInput  $team  The team input data containing competitors and their ratings.
     * @return float The overall rating for the team.
     */
    protected function teamRating(TeamInput $team): float
    {
        // Calculate the total weight of the team by summing the weights of all competitors.
        $weight = array_sum(array_map(static fn (CompetitorInput $competitor): float => $competitor->weight, $team->competitors));

        // If the total weight is less than or equal to zero, return a default rating of 0.0 to avoid division by zero.
        if ($weight <= 0.0) {
            return 0.0;
        }

        // Calculate the weighted sum of the competitors' ratings, where each competitor's rating is multiplied by their weight.
        $weighted = array_sum(array_map(
            static fn (CompetitorInput $competitor): float => $competitor->rating->rating * $competitor->weight,
            $team->competitors,
        ));

        // Determine the team aggregation method from the options, defaulting to 'average' if not set.
        return ($this->options['team_aggregation'] ?? 'average') === 'sum'
            ? $weighted
            : $weighted / $weight;
    }

    /**
     * Calculate the distribution factor for a competitor within a team.
     *
     * @param  TeamInput  $team  The team containing the competitor.
     * @param  CompetitorInput  $competitor  The competitor for whom to calculate the distribution factor.
     * @return float The distribution factor for the competitor.
     */
    protected function distributionFactor(TeamInput $team, CompetitorInput $competitor): float
    {
        // Determine the distribution mode from the options, defaulting to 'participation' if not set.
        $mode = (string) ($this->options['team_distribution'] ?? 'participation');
        $memberCount = count($team->competitors);

        // Handle different distribution modes to calculate the distribution factor for the competitor.
        if ($mode === 'equal') {
            return 1.0;
        }

        // Handle the 'rating_weighted' mode, where the distribution factor is based on the competitor's rating relative to the total team rating.
        if ($mode === 'rating_weighted') {
            $total = array_sum(array_map(
                static fn (CompetitorInput $member): float => max(1.0, $member->rating->rating),
                $team->competitors,
            ));

            // Calculate the distribution factor for the competitor based on their rating and the total team rating.
            return max(1.0, $competitor->rating->rating) * $memberCount / max(0.000001, $total);
        }

        // Handle the 'weight_weighted' mode, where the distribution factor is based on the competitor's weight relative to the total team weight.
        $total = array_sum(array_map(
            static fn (CompetitorInput $member): float => $member->weight,
            $team->competitors,
        ));

        // Calculate the distribution factor for the competitor based on their weight and the total team weight.
        return $competitor->weight * $memberCount / max(0.000001, $total);
    }

    /**
     * Calculate the expected score for a pair of teams based on their ratings.
     *
     * @param  float  $leftRating  The rating of the left team.
     * @param  float  $rightRating  The rating of the right team.
     * @param  TeamInput  $left  The left team input data.
     * @param  TeamInput  $right  The right team input data.
     * @return float The expected score for the left team against the right team.
     */
    abstract protected function expected(float $leftRating, float $rightRating, TeamInput $left, TeamInput $right): float;

    /**
     * Calculate the rating delta for a pair of teams based on their actual and expected scores.
     *
     * @param  TeamInput  $left  The left team input data.
     * @param  TeamInput  $right  The right team input data.
     * @param  float  $actual  The actual score for the left team against the right team.
     * @param  float  $expected  The expected score for the left team against the right team.
     * @param  float  $leftRating  The rating of the left team.
     * @param  float  $rightRating  The rating of the right team.
     * @return float The rating delta for the left team based on the match outcome.
     */
    protected function pairDelta(
        TeamInput $left,
        TeamInput $right,
        float $actual,
        float $expected,
        float $leftRating,
        float $rightRating,
    ): float {
        return $this->kFactor($left, $right, $leftRating, $rightRating) * ($actual - $expected);
    }

    /**
     * Get the K-factor for a pair of teams based on their ratings and input data.
     *
     * @param  TeamInput  $left  The left team input data.
     * @param  TeamInput  $right  The right team input data.
     * @param  float  $leftRating  The rating of the left team.
     * @param  float  $rightRating  The rating of the right team.
     * @return float The K-factor to be used in the rating calculation for the match.
     */
    protected function kFactor(TeamInput $left, TeamInput $right, float $leftRating, float $rightRating): float
    {
        return (float) ($this->options['k_factor'] ?? 32.0);
    }

    /**
     * Calculate the next rating state for a competitor based on the rating delta.
     *
     * @param  CompetitorInput  $competitor  The competitor input data.
     * @param  float  $delta  The rating delta to be applied to the competitor's current rating.
     * @return RatingState The new rating state for the competitor after applying the delta.
     */
    protected function nextState(CompetitorInput $competitor, float $delta): RatingState
    {
        // Calculate the new rating for the competitor, ensuring it is clamped within the specified floor and ceiling if provided.
        return new RatingState(
            rating: Math::clamp(
                $competitor->rating->rating + $delta,
                isset($this->options['rating_floor']) ? (float) $this->options['rating_floor'] : null,
                isset($this->options['rating_ceiling']) ? (float) $this->options['rating_ceiling'] : null,
            ),
            deviation: $competitor->rating->deviation,
            volatility: $competitor->rating->volatility,
            games: $competitor->rating->games + 1,
            provisional: $competitor->rating->provisional,
            metadata: $competitor->rating->metadata,
        );
    }
}
