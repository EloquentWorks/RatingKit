<?php

namespace EloquentWorks\RatingKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a rating season in the application.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $pool
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property array<string, mixed>|null $metadata
 */
class RatingSeason extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('rating-kit.tables.seasons', 'rating_kit_seasons');
    }

    /**
     * Get the casts for the model's attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the ratings associated with this season.
     *
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        /** @var class-string<Rating> $model */
        $model = config('rating-kit.models.rating', Rating::class);

        return $this->hasMany($model, 'season_id');
    }

    /**
     * Get the matches associated with this season.
     *
     * @return HasMany<RatingMatch, $this>
     */
    public function matches(): HasMany
    {
        /** @var class-string<RatingMatch> $model */
        $model = config('rating-kit.models.match', RatingMatch::class);

        return $this->hasMany($model, 'season_id');
    }

    /**
     * Scope a query to only include open seasons.
     *
     * @param Builder<RatingSeason> $query
     * @return void
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }
    
    /**
     * Determine if the season is open.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
