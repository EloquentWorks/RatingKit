<?php

namespace EloquentWorks\RatingKit\Models;

use EloquentWorks\RatingKit\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a rating match in the system.
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $external_id
 * @property string $pool
 * @property string $algorithm
 * @property array<string, mixed>|null $algorithm_options
 * @property int|null $season_id
 * @property MatchStatus $status
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $voided_at
 * @property string|null $void_reason
 * @property array<string, mixed>|null $metadata
 */
class RatingMatch extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    /**
     * Get the table name for the rating match model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return (string) config('rating-kit.tables.matches', 'rating_kit_matches');
    }

    /**
     * Get the casts for the model's attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'algorithm_options' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'voided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the season associated with the match.
     *
     * @return BelongsTo<RatingSeason, $this>
     */
    public function season(): BelongsTo
    {
        /** @var class-string<RatingSeason> $model */
        $model = config('rating-kit.models.season', RatingSeason::class);

        return $this->belongsTo($model, 'season_id');
    }

    /**
     * Get the teams associated with the match.
     *
     * @return HasMany<RatingTeam, $this>
     */
    public function teams(): HasMany
    {
        /** @var class-string<RatingTeam> $model */
        $model = config('rating-kit.models.team', RatingTeam::class);

        return $this->hasMany($model, 'match_id')->orderBy('position');
    }

    /**
     * Get the participants associated with the match.
     *
     * @return HasMany<RatingParticipant, $this>
     */
    public function participants(): HasMany
    {
        /** @var class-string<RatingParticipant> $model */
        $model = config('rating-kit.models.participant', RatingParticipant::class);

        return $this->hasMany($model, 'match_id');
    }

    /**
     * Determine if the match has been processed.
     *
     * @return bool
     */
    public function isProcessed(): bool
    {
        return $this->status === MatchStatus::Processed;
    }

    /**
     * Determine if the match has been voided.
     *
     * @return bool
     */
    public function isVoided(): bool
    {
        return $this->status === MatchStatus::Voided;
    }
}
