<?php

namespace EloquentWorks\RatingKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a snapshot of a leaderboard at a specific point in time.
 *
 * @property int $id
 * @property string $pool
 * @property string $algorithm
 * @property int|null $season_id
 * @property \Illuminate\Support\Carbon $captured_at
 * @property int $entry_count
 * @property list<array<string, mixed>> $entries
 * @property array<string, mixed>|null $metadata
 */
class LeaderboardSnapshot extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return (string) config('rating-kit.tables.leaderboard_snapshots', 'rating_kit_leaderboard_snapshots');
    }

    /**
     * Get the casts for the model's attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'entries' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the season associated with the leaderboard snapshot.
     *
     * @return BelongsTo<RatingSeason, $this>
     */
    public function season(): BelongsTo
    {
        /** @var class-string<RatingSeason> $model */
        $model = config('rating-kit.models.season', RatingSeason::class);

        return $this->belongsTo($model, 'season_id');
    }
}
