<?php

namespace EloquentWorks\RatingKit\Data;

/**
 * Class TeamInput
 *
 * Represents a team of competitors in a competition.
 */
final readonly class TeamInput
{
    /**
     * @param list<CompetitorInput> $competitors
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $competitors,
        public int $rank,
        public ?float $score = null,
        public ?string $name = null,
        public array $metadata = [],
    ) {
        // Validate the team input
        if ($this->competitors === []) {
            throw new \InvalidArgumentException('A team must contain at least one competitor.');
        }

        // Validate that the rank is one or greater
        if ($this->rank < 1) {
            throw new \InvalidArgumentException('A team rank must be one or greater.');
        }

        // Validate that the score is not negative
        $keys = array_map(static fn (CompetitorInput $competitor): string => $competitor->key, $this->competitors);

        // Validate that there are no duplicate competitors in the team
        if (count($keys) !== count(array_unique($keys))) {
            throw new \InvalidArgumentException('A competitor cannot appear twice on the same team.');
        }
    }

    /**
     * Determine if this team is drawn with another team.
     *
     * @param TeamInput $other The other team to compare against.
     *
     * @return bool Returns true if the teams are drawn, false otherwise.
     */
    public function isDrawnWith(self $other): bool
    {
        return $this->rank === $other->rank;
    }
}
