<?php

namespace EloquentWorks\RatingKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents a rating for a rateable model.
 *
 * @property int $id
 * @property string $rateable_type
 * @property int|string $rateable_id
 * @property string $pool
 * @property string $algorithm
 * @property int|null $season_id
 * @property int $season_key
 * @property float $rating
 * @property float $deviation
 * @property float $volatility
 * @property int $games
 * @property int $wins
 * @property int $draws
 * @property int $losses
 * @property int $streak
 * @property bool $provisional
 * @property \Illuminate\Support\Carbon|null $last_competed_at
 * @property \Illuminate\Support\Carbon|null $decayed_at
 * @property array<string, mixed>|null $metadata
 */
class Rating extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    /**
     * Get the table name for the rating model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return (string) config('rating-kit.tables.ratings', 'rating_kit_ratings');
    }

    /**
     * Get the casts for the model's attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'deviation' => 'float',
            'volatility' => 'float',
            'games' => 'integer',
            'wins' => 'integer',
            'draws' => 'integer',
            'losses' => 'integer',
            'streak' => 'integer',
            'provisional' => 'boolean',
            'last_competed_at' => 'datetime',
            'decayed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the rateable model that this rating belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function rateable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the season associated with the rating.
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
     * Get the history associated with the rating.
     *
     * @return HasMany<RatingHistory, $this>
     */
    public function history(): HasMany
    {
        /** @var class-string<RatingHistory> $model */
        $model = config('rating-kit.models.history', RatingHistory::class);

        return $this->hasMany($model, 'rating_id');
    }

    /**
     * Get the participations associated with the rating.
     *
     * @return HasMany<RatingParticipant, $this>
     */
    public function participations(): HasMany
    {
        /** @var class-string<RatingParticipant> $model */
        $model = config('rating-kit.models.participant', RatingParticipant::class);

        return $this->hasMany($model, 'rating_id');
    }

    /**
     * Scope a query to only include ratings in a specific pool.
     *
     * @param Builder<Rating> $query
     * @param string $pool
     * @return void
     */
    public function scopeInPool(Builder $query, string $pool): void
    {
        $query->where('pool', $pool);
    }

    /**
     * Scope a query to only include ratings using a specific algorithm.
     *
     * @param Builder<Rating> $query
     * @param string $algorithm
     * @return void
     */
    public function scopeUsingAlgorithm(Builder $query, string $algorithm): void
    {
        $query->where('algorithm', $algorithm);
    }

    /**
     * Scope a query to only include ratings for a specific season.
     *
     * @param Builder<Rating> $query
     * @param int|null $seasonId
     * @return void
     */
    public function scopeForSeason(Builder $query, ?int $seasonId): void
    {
        $query->where('season_key', $seasonId ?? 0);
    }

    /**
     * Scope a query to only include established ratings.
     *
     * @param Builder<Rating> $query
     * @return void
     */
    public function scopeEstablished(Builder $query): void
    {
        $query->where('provisional', false);
    }
}
