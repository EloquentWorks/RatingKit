<?php

namespace EloquentWorks\RatingKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a team in a rating match.
 *
 * @property int $id
 * @property int $match_id
 * @property int $position
 * @property int $rank
 * @property float|null $score
 * @property string|null $name
 * @property array<string, mixed>|null $metadata
 */
class RatingTeam extends Model
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
        return (string) config('rating-kit.tables.teams', 'rating_kit_teams');
    }

    /**
     * Get the casts for the model's attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the match associated with the team.
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
     * Get the participants associated with the team.
     *
     * @return HasMany<RatingParticipant, $this>
     */
    public function participants(): HasMany
    {
        /** @var class-string<RatingParticipant> $model */
        $model = config('rating-kit.models.participant', RatingParticipant::class);

        return $this->hasMany($model, 'team_id');
    }
}
