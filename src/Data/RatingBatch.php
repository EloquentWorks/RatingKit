<?php

namespace EloquentWorks\RatingKit\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<string, RatingChange>
 */
final readonly class RatingBatch implements Countable, IteratorAggregate
{
    /**
     * @param  array<string, RatingChange>  $changes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $changes,
        public array $metadata = [],
    ) {}

    /**
     * Get the rating change for a specific competitor.
     *
     * @param  string  $key  The key of the competitor
     * @return RatingChange The rating change for the specified competitor
     *
     * @throws \OutOfBoundsException If no rating change exists for the specified competitor
     */
    public function get(string $key): RatingChange
    {
        return $this->changes[$key] ?? throw new \OutOfBoundsException("No rating change exists for competitor [{$key}].");
    }

    /**
     * Get the number of rating changes in the batch.
     *
     * @return int The number of rating changes
     */
    public function count(): int
    {
        return count($this->changes);
    }

    /**
     * @return Traversable<string, RatingChange>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->changes);
    }
}
