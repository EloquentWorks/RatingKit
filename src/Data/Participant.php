<?php

namespace EloquentWorks\RatingKit\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a participant in a rating system.
 */
final readonly class Participant
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public Model $rateable,
        public float $weight = 1.0,
        public array $metadata = [],
    ) {
        // Ensure the rateable model is persisted and the weight is valid
        if (! $this->rateable->exists) {
            throw new \InvalidArgumentException('A rating participant must be a persisted Eloquent model.');
        }

        // Ensure the weight is greater than zero
        if ($this->weight <= 0.0) {
            throw new \InvalidArgumentException('A participant weight must be greater than zero.');
        }
    }

    /**
     * Get the unique key for the participant, which is a combination of the rateable model's morph class and its primary key.
     *
     * @return string Returns the unique key for the participant.
     */
    public function key(): string
    {
        // The unique key is a combination of the rateable model's morph class and its primary key
        return $this->rateable->getMorphClass().':'.$this->rateable->getKey();
    }
}
