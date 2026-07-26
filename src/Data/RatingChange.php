<?php

namespace EloquentWorks\RatingKit\Data;

/**
 * Class RatingChange
 *
 * Represents a change in rating between two states.
 */
final readonly class RatingChange
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $key,
        public RatingState $before,
        public RatingState $after,
        public array $metadata = [],
    ) {}

    /**
     * Calculate the change in rating between the before and after states.
     *
     * @return float Returns the difference in rating.
     */
    public function delta(): float
    {
        return $this->after->rating - $this->before->rating;
    }
}
