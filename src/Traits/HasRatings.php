<?php

namespace EloquentWorks\RatingKit\Traits;

use EloquentWorks\RatingKit\Models\Rating;
use EloquentWorks\RatingKit\Models\RatingHistory;
use EloquentWorks\RatingKit\Models\RatingMatch;
use EloquentWorks\RatingKit\Models\RatingParticipant;
use EloquentWorks\RatingKit\RatingKitManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Give an Eloquent model one or more independent RatingKit ratings.
 *
 * A rateable model may have a separate rating for every combination of pool,
 * algorithm, and season. Add this trait to User, Team, Bot, Clan, or any other
 * model that can participate in rated matches.
 */
trait HasRatings
{
    /** @return MorphMany<Rating, $this> */
    public function ratings(): MorphMany
    {
        /** @var class-string<Rating> $model */
        $model = config('rating-kit.models.rating', Rating::class);

        return $this->morphMany($model, 'rateable');
    }

    /** @return MorphMany<RatingParticipant, $this> */
    public function ratingParticipations(): MorphMany
    {
        /** @var class-string<RatingParticipant> $model */
        $model = config('rating-kit.models.participant', RatingParticipant::class);

        return $this->morphMany($model, 'rateable');
    }

    /**
     * Return one rating identity, optionally creating it with configured defaults.
     */
    public function ratingFor(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        bool $create = true,
    ): ?Rating {
        $pool = $this->ratingKitPool($pool);
        $algorithm = $this->ratingKitAlgorithm($algorithm);
        $seasonKey = $seasonId ?? 0;

        $query = $this->ratings()
            ->where('pool', $pool)
            ->where('algorithm', $algorithm)
            ->where('season_key', $seasonKey);

        $rating = $query->first();

        if ($rating !== null || ! $create) {
            return $rating;
        }

        return $this->ratings()->create([
            'pool' => $pool,
            'algorithm' => $algorithm,
            'season_id' => $seasonId,
            'season_key' => $seasonKey,
            'rating' => (float) config('rating-kit.initial.rating', 1500.0),
            'deviation' => (float) config('rating-kit.initial.deviation', 350.0),
            'volatility' => (float) config('rating-kit.initial.volatility', 0.06),
            'provisional' => true,
        ]);
    }

    public function hasRating(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): bool {
        return $this->ratingFor($pool, $algorithm, $seasonId, false) !== null;
    }

    public function currentRating(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): float {
        return $this->ratingFor($pool, $algorithm, $seasonId)?->rating
            ?? (float) config('rating-kit.initial.rating', 1500.0);
    }

    public function ratingDeviation(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): float {
        return $this->ratingFor($pool, $algorithm, $seasonId)?->deviation
            ?? (float) config('rating-kit.initial.deviation', 350.0);
    }

    public function ratingVolatility(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): float {
        return $this->ratingFor($pool, $algorithm, $seasonId)?->volatility
            ?? (float) config('rating-kit.initial.volatility', 0.06);
    }

    public function isProvisional(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): bool {
        return $this->ratingFor($pool, $algorithm, $seasonId)?->provisional ?? true;
    }

    public function hasEstablishedRating(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): bool {
        $rating = $this->ratingFor($pool, $algorithm, $seasonId, false);

        return $rating !== null && ! $rating->provisional;
    }

    /**
     * @return array{
     *     rating: float,
     *     deviation: float,
     *     volatility: float,
     *     games: int,
     *     wins: int,
     *     draws: int,
     *     losses: int,
     *     win_rate: float,
     *     streak: int,
     *     provisional: bool
     * }
     */
    public function ratingStats(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): array {
        $rating = $this->ratingFor($pool, $algorithm, $seasonId, false);

        if ($rating === null) {
            return [
                'rating' => (float) config('rating-kit.initial.rating', 1500.0),
                'deviation' => (float) config('rating-kit.initial.deviation', 350.0),
                'volatility' => (float) config('rating-kit.initial.volatility', 0.06),
                'games' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'win_rate' => 0.0,
                'streak' => 0,
                'provisional' => true,
            ];
        }

        return [
            'rating' => $rating->rating,
            'deviation' => $rating->deviation,
            'volatility' => $rating->volatility,
            'games' => $rating->games,
            'wins' => $rating->wins,
            'draws' => $rating->draws,
            'losses' => $rating->losses,
            'win_rate' => $rating->games > 0 ? ($rating->wins / $rating->games) * 100.0 : 0.0,
            'streak' => $rating->streak,
            'provisional' => $rating->provisional,
        ];
    }

    public function ratedGames(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): int {
        return $this->ratingFor($pool, $algorithm, $seasonId, false)?->games ?? 0;
    }

    public function ratingWins(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): int {
        return $this->ratingFor($pool, $algorithm, $seasonId, false)?->wins ?? 0;
    }

    public function ratingDraws(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): int {
        return $this->ratingFor($pool, $algorithm, $seasonId, false)?->draws ?? 0;
    }

    public function ratingLosses(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): int {
        return $this->ratingFor($pool, $algorithm, $seasonId, false)?->losses ?? 0;
    }

    public function ratingWinRate(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): float {
        $rating = $this->ratingFor($pool, $algorithm, $seasonId, false);

        return $rating !== null && $rating->games > 0
            ? ($rating->wins / $rating->games) * 100.0
            : 0.0;
    }

    public function ratingStreak(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): int {
        return $this->ratingFor($pool, $algorithm, $seasonId, false)?->streak ?? 0;
    }

    /**
     * Return the lower-confidence leaderboard score used by conservative ranking.
     */
    public function conservativeRating(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        float $deviationMultiplier = 2.0,
    ): float {
        $algorithm = $this->ratingKitAlgorithm($algorithm);
        $rating = $this->ratingFor($pool, $algorithm, $seasonId, false);
        $value = $rating->rating ?? (float) config('rating-kit.initial.rating', 1500.0);
        $deviation = $rating?->deviation ?? (float) config('rating-kit.initial.deviation', 350.0);
        $direction = (string) config("rating-kit.algorithm_directions.{$algorithm}", 'desc');

        return $direction === 'asc'
            ? $value + ($deviationMultiplier * $deviation)
            : $value - ($deviationMultiplier * $deviation);
    }

    public function ratingRank(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        bool $includeProvisional = false,
        bool $conservative = false,
    ): ?int {
        return app(RatingKitManager::class)->rankOf(
            $this,
            $pool,
            $algorithm,
            $seasonId,
            $includeProvisional,
            $conservative,
        );
    }

    /**
     * @return Collection<int, Rating>
     */
    public function ratingsForPool(string $pool, ?int $seasonId = null): Collection
    {
        $query = $this->ratings()->where('pool', $pool);

        if ($seasonId !== null) {
            $query->where('season_key', $seasonId);
        }

        return $query->orderBy('algorithm')->get();
    }

    /**
     * @return Collection<int, Rating>
     */
    public function ratingsUsingAlgorithm(string $algorithm, ?int $seasonId = null): Collection
    {
        $query = $this->ratings()->where('algorithm', $algorithm);

        if ($seasonId !== null) {
            $query->where('season_key', $seasonId);
        }

        return $query->orderBy('pool')->get();
    }

    /**
     * @return Collection<int, Rating>
     */
    public function seasonRatings(?int $seasonId): Collection
    {
        return $this->ratings()
            ->where('season_key', $seasonId ?? 0)
            ->orderBy('pool')
            ->orderBy('algorithm')
            ->get();
    }

    /** @return Collection<int, string> */
    public function ratingPools(): Collection
    {
        return $this->ratings()
            ->select('pool')
            ->distinct()
            ->orderBy('pool')
            ->pluck('pool')
            ->map(static fn (mixed $pool): string => (string) $pool)
            ->values();
    }

    /** @return Collection<int, string> */
    public function ratingAlgorithms(?string $pool = null): Collection
    {
        $query = $this->ratings()->select('algorithm')->distinct();

        if ($pool !== null) {
            $query->where('pool', $pool);
        }

        return $query
            ->orderBy('algorithm')
            ->pluck('algorithm')
            ->map(static fn (mixed $algorithm): string => (string) $algorithm)
            ->values();
    }

    /**
     * @return Collection<int, RatingHistory>
     */
    public function ratingHistory(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        ?int $limit = null,
    ): Collection {
        $rating = $this->ratingFor($pool, $algorithm, $seasonId, false);

        if ($rating === null) {
            return collect();
        }

        $query = $rating->history()->latest('id');

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        return $query->get();
    }

    public function latestRatingChange(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): ?RatingHistory {
        $rating = $this->ratingFor($pool, $algorithm, $seasonId, false);

        return $rating?->history()->latest('id')->first();
    }

    /**
     * @return Collection<int, RatingParticipant>
     */
    public function recentRatingParticipations(
        int $limit = 20,
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): Collection {
        $query = $this->ratingParticipations()
            ->with(['match', 'team', 'rating'])
            ->latest('id');

        if ($pool !== null || $algorithm !== null || $seasonId !== null) {
            $query->whereHas('rating', function (Builder $ratingQuery) use ($pool, $algorithm, $seasonId): void {
                if ($pool !== null) {
                    $ratingQuery->where('pool', $pool);
                }

                if ($algorithm !== null) {
                    $ratingQuery->where('algorithm', $algorithm);
                }

                if ($seasonId !== null) {
                    $ratingQuery->where('season_key', $seasonId);
                }
            });
        }

        return $query->limit(max(1, $limit))->get();
    }

    public function latestRatingParticipation(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): ?RatingParticipant {
        return $this->recentRatingParticipations(1, $pool, $algorithm, $seasonId)->first();
    }

    /** @return Builder<RatingMatch> */
    public function ratedMatches(): Builder
    {
        /** @var class-string<RatingMatch> $model */
        $model = config('rating-kit.models.match', RatingMatch::class);
        $morphType = $this->getMorphClass();
        $morphId = $this->getKey();

        return $model::query()->whereHas(
            'participants',
            static function (Builder $query) use ($morphType, $morphId): void {
                $query
                    ->where('rateable_type', $morphType)
                    ->where('rateable_id', $morphId);
            },
        );
    }

    /** @return Collection<int, RatingMatch> */
    public function recentRatedMatches(int $limit = 20): Collection
    {
        return $this->ratedMatches()
            ->with(['teams.participants', 'participants'])
            ->latest('occurred_at')
            ->limit(max(1, $limit))
            ->get();
    }

    public function peakRating(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): float {
        $algorithm = $this->ratingKitAlgorithm($algorithm);
        $rating = $this->ratingFor($pool, $algorithm, $seasonId, false);

        if ($rating === null) {
            return (float) config('rating-kit.initial.rating', 1500.0);
        }

        $direction = (string) config("rating-kit.algorithm_directions.{$algorithm}", 'desc');
        $historical = $direction === 'asc'
            ? $rating->history()->min('rating_after')
            : $rating->history()->max('rating_after');

        return (float) ($direction === 'asc'
            ? min($rating->rating, $historical ?? $rating->rating)
            : max($rating->rating, $historical ?? $rating->rating));
    }

    public function adjustRating(
        float $amount,
        string $reason = 'manual_adjustment',
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        array $metadata = [],
    ): Rating {
        return app(RatingKitManager::class)->adjust(
            $this,
            $amount,
            $reason,
            $pool,
            $algorithm,
            $seasonId,
            $metadata,
        );
    }

    public function setRating(
        float $value,
        string $reason = 'manual_set',
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        array $metadata = [],
    ): Rating {
        return app(RatingKitManager::class)->setRating(
            $this,
            $value,
            $reason,
            $pool,
            $algorithm,
            $seasonId,
            $metadata,
        );
    }

    public function resetRating(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        string $reason = 'manual_reset',
    ): Rating {
        return app(RatingKitManager::class)->resetRating(
            $this,
            $pool,
            $algorithm,
            $seasonId,
            $reason,
        );
    }

    protected function ratingKitPool(?string $pool): string
    {
        return $pool ?? (string) config('rating-kit.default_pool', 'default');
    }

    protected function ratingKitAlgorithm(?string $algorithm): string
    {
        return $algorithm ?? (string) config('rating-kit.default_algorithm', 'glicko2');
    }
}
