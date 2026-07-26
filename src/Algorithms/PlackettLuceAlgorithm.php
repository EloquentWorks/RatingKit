<?php

namespace EloquentWorks\RatingKit\Algorithms;

use EloquentWorks\RatingKit\Data\CompetitorInput;
use EloquentWorks\RatingKit\Data\MatchInput;
use EloquentWorks\RatingKit\Data\RatingBatch;
use EloquentWorks\RatingKit\Data\RatingChange;
use EloquentWorks\RatingKit\Data\RatingState;
use EloquentWorks\RatingKit\Data\TeamInput;
use EloquentWorks\RatingKit\Support\Math;

/**
 * Implements the Plackett-Luce rating algorithm for pairwise comparisons.
 */
class PlackettLuceAlgorithm extends AbstractPairwiseAlgorithm
{
    /**
     * Returns the unique key identifying this algorithm.
     *
     * @return string The algorithm key.
     */
    public function key(): string
    {
        return 'plackett_luce';
    }

    /**
     * Calculates the expected score for a team based on its rating and the opponent's rating.
     *
     * @param float $leftRating The rating of the left team.
     * @param float $rightRating The rating of the right team.
     * @param TeamInput $left The left team input data.
     * @param TeamInput $right The right team input data.
     *
     * @return float The expected score for the left team.
     */
    protected function expected(float $leftRating, float $rightRating, TeamInput $left, TeamInput $right): float
    {
        return Math::logistic($leftRating - $rightRating, (float) ($this->options['scale'] ?? 400.0));
    }

    /**
     * Rates a match and returns the resulting rating changes for all competitors.
     *
     * @param MatchInput $match The match input data.
     *
     * @return RatingBatch The batch of rating changes resulting from the match.
     */
    public function rate(MatchInput $match): RatingBatch
    {
        // Calculate the ratings for each team in the match
        $teamRatings = array_map(fn (TeamInput $team): float => $this->teamRating($team), $match->teams);
        $teamDeltas = [];
        $teamCount = count($match->teams);
        $k = (float) ($this->options['k_factor'] ?? 24.0);
        $scale = (float) ($this->options['scale'] ?? 400.0);

        // Calculate the deltas for each team based on their performance against opponents
        foreach ($match->teams as $index => $team) {
            $actual = 0.0;
            $expected = 0.0;

            // Calculate the actual and expected scores for the team against each opponent
            foreach ($match->teams as $opponentIndex => $opponent) {
                if ($index === $opponentIndex) {
                    continue;
                }

                // Update actual score based on the team's rank relative to the opponent's rank
                $actual += $team->rank === $opponent->rank ? 0.5 : ($team->rank < $opponent->rank ? 1.0 : 0.0);
                $expected += Math::logistic($teamRatings[$index] - $teamRatings[$opponentIndex], $scale);
            }

            // Calculate the divisor for averaging the scores, ensuring it's at least 1 to avoid division by zero
            $divisor = max(1, $teamCount - 1);
            $teamDeltas[$index] = $k * (($actual / $divisor) - ($expected / $divisor));
        }

        // Initialize an array to hold the rating changes for each competitor
        $changes = [];

        // Update each competitor's rating based on the calculated team deltas and their participation factor
        foreach ($match->teams as $teamIndex => $team) {
            foreach ($team->competitors as $competitor) {
                $factor = $this->distributionFactor($team, $competitor);
                $delta = $teamDeltas[$teamIndex] * $factor;

                // Create a new RatingState for the competitor after applying the delta, ensuring it respects any
                // rating floor or ceiling constraints
                $after = new RatingState(
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

                // Store the rating change for the competitor, including the team delta and participation factor in the metadata
                $changes[$competitor->key] = new RatingChange($competitor->key, $competitor->rating, $after, [
                    'team_delta' => $teamDeltas[$teamIndex],
                    'participation_factor' => $factor,
                ]);
            }
        }

        // Return a new RatingBatch containing all the rating changes and the algorithm key
        return new RatingBatch($changes, ['algorithm' => $this->key()]);
    }
}
