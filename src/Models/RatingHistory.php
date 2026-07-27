<?php

namespace EloquentWorks\RatingKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a history record of a rating change.
 *
 * @property int $id
 * @property int $rating_id
 * @property int|null $match_id
 * @property string $reason
 * @property float $rating_before
 * @property float $rating_after
 * @property float $deviation_before
 * @property float $deviation_after
 * @property float $volatility_before
 * @property float $volatility_after
 * @property array<string, mixed>|null $metadata
 */
class RatingHistory extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    /**
     * Get the table name for the rating history model.
     */
    public function getTable(): string
    {
        return (string) config('rating-kit.tables.histories', 'rating_kit_histories');
    }

    /**
     * Get the casts for the model's attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating_before' => 'float',
            'rating_after' => 'float',
            'deviation_before' => 'float',
            'deviation_after' => 'float',
            'volatility_before' => 'float',
            'volatility_after' => 'float',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the rating associated with the history record.
     *
     * @return BelongsTo<Rating, $this>
     */
    public function rating(): BelongsTo
    {
        /** @var class-string<Rating> $model */
        $model = config('rating-kit.models.rating', Rating::class);

        return $this->belongsTo($model, 'rating_id');
    }

    /**
     * Get the match associated with the history record.
     *
     * @return BelongsTo<RatingMatch, $this>
     */
    public function match(): BelongsTo
    {
        /** @var class-string<RatingMatch> $model */
        $model = config('rating-kit.models.match', RatingMatch::class);

        return $this->belongsTo($model, 'match_id');
    }
}
