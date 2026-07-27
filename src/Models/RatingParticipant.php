<?php

namespace EloquentWorks\RatingKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents a participant in a rating match, including their associated team, rating, and performance metrics.
 *
 * @property int $id
 * @property int $match_id
 * @property int $team_id
 * @property int $rating_id
 * @property string $rateable_type
 * @property int|string $rateable_id
 * @property string $outcome
 * @property float $weight
 * @property float $rating_before
 * @property float $rating_after
 * @property float $rating_delta
 * @property float $deviation_before
 * @property float $deviation_after
 * @property float $volatility_before
 * @property float $volatility_after
 * @property array<string, mixed>|null $metadata
 */
class RatingParticipant extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    /**
     * Get the table name for the rating participant model.
     */
    public function getTable(): string
    {
        return (string) config('rating-kit.tables.participants', 'rating_kit_participants');
    }

    /**
     * Get the attribute casting definitions for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'rating_before' => 'float',
            'rating_after' => 'float',
            'rating_delta' => 'float',
            'deviation_before' => 'float',
            'deviation_after' => 'float',
            'volatility_before' => 'float',
            'volatility_after' => 'float',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the match associated with the participant.
     *
     * @return BelongsTo<RatingMatch, $this>
     */
    public function match(): BelongsTo
    {
        /** @var class-string<RatingMatch> $model */
        $model = config('rating-kit.models.match', RatingMatch::class);

        return $this->belongsTo($model, 'match_id');
    }

    /**
     * Get the team associated with the participant.
     *
     * @return BelongsTo<RatingTeam, $this>
     */
    public function team(): BelongsTo
    {
        /** @var class-string<RatingTeam> $model */
        $model = config('rating-kit.models.team', RatingTeam::class);

        return $this->belongsTo($model, 'team_id');
    }

    /**
     * Get the rating associated with the participant.
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
     * Get the rateable model associated with the participant.
     *
     * @return MorphTo<Model, $this>
     */
    public function rateable(): MorphTo
    {
        return $this->morphTo();
    }
}
