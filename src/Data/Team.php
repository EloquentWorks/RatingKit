<?php

namespace EloquentWorks\RatingKit\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a team in a competition or game.
 */
final readonly class Team
{
    /**
     * @param list<Participant|Model> $participants
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $participants,
        public int $rank,
        public ?float $score = null,
        public ?string $name = null,
        public array $metadata = [],
    ) {
        // Validate the team data
        if ($this->participants === []) {
            throw new \InvalidArgumentException('A team must contain at least one participant.');
        }

        // Validate the rank
        if ($this->rank < 1) {
            throw new \InvalidArgumentException('A team rank must be one or greater.');
        }
    }

    /**
     * @return list<Participant>
     */
    public function normalizedParticipants(): array
    {
        // Normalize the participants to ensure they are all instances of Participant
        return array_map(
            static fn (Participant|Model $participant): Participant => $participant instanceof Participant
                ? $participant
                : new Participant($participant),
            $this->participants,
        );
    }
}
