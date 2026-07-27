<?php

namespace EloquentWorks\RatingKit\Data;

use Carbon\CarbonInterface;

/**
 * Class RecordMatch
 *
 * Represents a match between teams for rating purposes.
 */
final readonly class RecordMatch
{
    /**
     * @param  list<Team>  $teams
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $teams,
        public ?string $algorithm = null,
        public ?string $pool = null,
        public ?int $seasonId = null,
        public ?string $externalId = null,
        public ?CarbonInterface $occurredAt = null,
        public array $metadata = [],
    ) {
        // Validate that there are at least two teams in the match
        if (count($this->teams) < 2) {
            throw new \InvalidArgumentException('A rated match must contain at least two teams.');
        }
    }
}
