<?php

namespace EloquentWorks\RatingKit\Data;

/**
 * Class RatingState
 *
 * Represents the state of a player's rating in a rating system.
 */
final readonly class RatingState
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public float $rating = 1500.0,
        public float $deviation = 350.0,
        public float $volatility = 0.06,
        public int $games = 0,
        public bool $provisional = true,
        public array $metadata = [],
    ) {}
}
