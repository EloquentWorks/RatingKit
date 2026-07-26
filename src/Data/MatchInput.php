<?php

namespace EloquentWorks\RatingKit\Data;

/**
 * Class MatchInput
 *
 * Represents the input data for a match, including teams, draw margin, and metadata.
 */
final readonly class MatchInput
{
    /**
     * @param list<TeamInput> $teams
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $teams,
        public ?float $drawMargin = null,
        public array $metadata = [],
    ) {
        // Validate that there are at least two teams in the match
        if (count($this->teams) < 2) {
            throw new \InvalidArgumentException('A rated match must contain at least two teams.');
        }

        // Validate that no competitor appears on multiple teams in the same match
        $keys = [];

        // Check for duplicate competitors across teams
        foreach ($this->teams as $team) {
            foreach ($team->competitors as $competitor) {
                if (isset($keys[$competitor->key])) {
                    throw new \InvalidArgumentException('A competitor cannot appear on multiple teams in the same match.');
                }

                // Mark this competitor as seen
                $keys[$competitor->key] = true;
            }
        }
    }

    /**
     * Get the total number of competitors across all teams in the match.
     *
     * @return int Returns the total count of competitors.
     */
    public function competitorCount(): int
    {
        return array_sum(array_map(static fn (TeamInput $team): int => count($team->competitors), $this->teams));
    }
}
