<?php

namespace EloquentWorks\RatingKit\Data;

/**
 * Class CompetitorInput
 *
 * Represents the input data for a competitor in a rating system.
 */
final readonly class CompetitorInput
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $key,
        public RatingState $rating,
        public float $weight = 1.0,
        public array $metadata = [],
    ) {
        // Validate the competitor input data
        if ($this->key === '') {
            throw new \InvalidArgumentException('A competitor key cannot be empty.');
        }

        // Validate the weight of the competitor
        if ($this->weight <= 0.0) {
            throw new \InvalidArgumentException('A competitor weight must be greater than zero.');
        }
    }
}
