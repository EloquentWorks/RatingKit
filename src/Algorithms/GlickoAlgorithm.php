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
 * Implements the Glicko rating algorithm for rating competitors in matches.
 */
class GlickoAlgorithm implements RatingAlgorithm
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(protected array $options = []) {}

    /**
     * Get the unique key for this rating algorithm.
     *
     * @return string Returns the unique key for this rating algorithm.
     */
    public function key(): string
    {
        return 'glicko';
    }

    /**
     * Get the display name for this rating algorithm.
     *
     * @return string Returns the display name for this rating algorithm.
     */
    public function supportsTeams(): bool
    {
        return true;
    }

    /**
     * Determine if this rating algorithm supports multiple teams.
     *
     * @return bool Returns true if this rating algorithm supports multiple teams, false otherwise.
     */
    public function supportsMultipleTeams(): bool
    {
        return true;
    }

    /**
     * Rate a match and return the rating changes for each competitor.
     *
     * @param  MatchInput  $match  The match input containing teams and competitors.
     * @return RatingBatch Returns a batch of rating changes for each competitor.
     */
    public function rate(MatchInput $match): RatingBatch
    {
        // Initialize an array to hold the rating changes for each competitor
        $changes = [];

        // Loop through each team in the match
        foreach ($match->teams as $teamIndex => $team) {
            foreach ($team->competitors as $competitor) {
                $opponents = [];

                // Loop through each team in the match again to find opponents for the current competitor
                foreach ($match->teams as $opponentIndex => $opponent) {
                    if ($teamIndex === $opponentIndex) {
                        continue;
                    }

                    // Calculate the rating, deviation, and score for the opponent team
                    $opponents[] = [
                        'rating' => $this->teamRating($opponent),
                        'deviation' => $this->teamDeviation($opponent),
                        'score' => $team->rank === $opponent->rank ? 0.5 : ($team->rank < $opponent->rank ? 1.0 : 0.0),
                    ];
                }

                // Calculate the rating change for the current competitor based on their opponents and store it in the changes array
                $changes[$competitor->key] = $this->rateCompetitor($competitor, $opponents);
            }
        }

        // Return a new RatingBatch containing the rating changes and the algorithm key
        return new RatingBatch($changes, ['algorithm' => $this->key()]);
    }

    /**
     * @param  list<array{rating: float, deviation: float, score: float}>  $opponents
     */
    protected function rateCompetitor(CompetitorInput $competitor, array $opponents): RatingChange
    {
        // Calculate the Glicko rating change for a single competitor based on their opponents
        $q = log(10.0) / 400.0;
        $rating = $competitor->rating->rating;
        $deviation = min(350.0, max(30.0, $competitor->rating->deviation));
        $varianceSum = 0.0;
        $scoreSum = 0.0;

        // Loop through each opponent and calculate the expected score and variance for the competitor
        foreach ($opponents as $opponent) {
            $g = 1.0 / sqrt(1.0 + (3.0 * $q * $q * $opponent['deviation'] ** 2) / (M_PI ** 2));
            $expected = 1.0 / (1.0 + 10.0 ** (-$g * ($rating - $opponent['rating']) / 400.0));
            $varianceSum += $g * $g * $expected * (1.0 - $expected);
            $scoreSum += $g * ($opponent['score'] - $expected);
        }

        // If the variance sum is less than or equal to zero, return the current rating without any changes
        if ($varianceSum <= 0.0) {
            return new RatingChange($competitor->key, $competitor->rating, $competitor->rating);
        }

        // Calculate the new rating and deviation for the competitor based on the Glicko formula
        $dSquared = 1.0 / ($q * $q * $varianceSum);
        $newDeviation = sqrt(1.0 / (1.0 / ($deviation ** 2) + 1.0 / $dSquared));
        $newRating = $rating + ($q / (1.0 / ($deviation ** 2) + 1.0 / $dSquared)) * $scoreSum;

        // Create a new RatingState object for the competitor after the rating change, ensuring that the
        // new rating is clamped within the specified floor and ceiling if provided in the options
        $after = new RatingState(
            rating: Math::clamp(
                $newRating,
                isset($this->options['rating_floor']) ? (float) $this->options['rating_floor'] : null,
                isset($this->options['rating_ceiling']) ? (float) $this->options['rating_ceiling'] : null,
            ),
            deviation: $newDeviation,
            volatility: $competitor->rating->volatility,
            games: $competitor->rating->games + 1,
            provisional: $competitor->rating->provisional,
            metadata: $competitor->rating->metadata,
        );

        // Return a new RatingChange object containing the competitor's key, their rating before the change, and
        // their rating after the change
        return new RatingChange($competitor->key, $competitor->rating, $after);
    }

    /**
     * Calculate the aggregated rating for a team based on its competitors.
     *
     * @param  TeamInput  $team  The team input containing competitors.
     * @return float Returns the aggregated rating for the team.
     */
    protected function teamRating(TeamInput $team): float
    {
        // Calculate the total weight of the competitors in the team
        $weight = array_sum(array_map(static fn (CompetitorInput $competitor): float => $competitor->weight, $team->competitors));

        // Calculate the weighted sum of ratings for the competitors in the team
        $weighted = array_sum(array_map(
            static fn (CompetitorInput $competitor): float => $competitor->rating->rating * $competitor->weight,
            $team->competitors,
        ));

        // Return the aggregated rating for the team based on the specified aggregation method (sum or average)
        return ($this->options['team_aggregation'] ?? 'average') === 'sum'
            ? $weighted
            : $weighted / max($weight, 0.000001);
    }

    /**
     * Calculate the aggregated deviation for a team based on its competitors.
     *
     * @param  TeamInput  $team  The team input containing competitors.
     * @return float Returns the aggregated deviation for the team.
     */
    protected function teamDeviation(TeamInput $team): float
    {
        // Calculate the total weight of the competitors in the team
        $weight = array_sum(array_map(static fn (CompetitorInput $competitor): float => $competitor->weight, $team->competitors));
        $variance = array_sum(array_map(
            static fn (CompetitorInput $competitor): float => ($competitor->rating->deviation * $competitor->weight) ** 2,
            $team->competitors,
        ));

        // Return the aggregated deviation for the team based on the specified aggregation method (sum or average)
        return ($this->options['team_aggregation'] ?? 'average') === 'sum'
            ? sqrt($variance)
            : sqrt($variance) / max($weight, 0.000001);
    }
}
