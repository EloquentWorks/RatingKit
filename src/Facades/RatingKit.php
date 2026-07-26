<?php

namespace EloquentWorks\RatingKit\Facades;

use EloquentWorks\RatingKit\Data\RecordMatch;
use EloquentWorks\RatingKit\Data\Team;
use EloquentWorks\RatingKit\Models\LeaderboardSnapshot;
use EloquentWorks\RatingKit\Models\Rating;
use EloquentWorks\RatingKit\Models\RatingMatch;
use EloquentWorks\RatingKit\Models\RatingSeason;
use EloquentWorks\RatingKit\RatingKitManager;
use EloquentWorks\RatingKit\Support\AlgorithmRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the RatingKitManager.
 *
 * @method static AlgorithmRegistry algorithms()
 * @method static RatingMatch record(RecordMatch $request)
 * @method static RatingMatch oneVsOne(Model $left, Model $right, string $result = 'left', ?string $algorithm = null, ?string $pool = null, ?int $seasonId = null, ?string $externalId = null, array<string, mixed> $metadata = [])
 * @method static RatingMatch teamVsTeam(array $left, array $right, string $result = 'left', ?string $algorithm = null, ?string $pool = null, ?int $seasonId = null, array<string, mixed> $metadata = [])
 * @method static RatingMatch freeForAll(array $placements, ?string $algorithm = null, ?string $pool = null, ?int $seasonId = null, array<string, mixed> $metadata = [])
 * @method static Collection<int, array{rank: int, rating: Rating, score: float}> leaderboard(?string $pool = null, ?string $algorithm = null, ?int $seasonId = null, int $limit = 100, bool $includeProvisional = false, bool $conservative = false)
 * @method static list<array{team: int, rating: float, probability: float}> predict(list<Team> $teams, ?string $pool = null, ?string $algorithm = null, ?int $seasonId = null)
 * @method static float matchQuality(list<Team> $teams, ?string $pool = null, ?string $algorithm = null, ?int $seasonId = null)
 * @method static array{count: int, established: int, provisional: int, average: float|null, minimum: float|null, maximum: float|null, median: float|null} poolStatistics(?string $pool = null, ?string $algorithm = null, ?int $seasonId = null)
 * @method static RatingMatch void(RatingMatch $match, ?string $reason = null)
 * @method static LeaderboardSnapshot snapshotLeaderboard(?string $pool = null, ?string $algorithm = null, ?int $seasonId = null, int $limit = 100)
 * @method static RatingSeason createSeason(string $name, string $slug, ?string $pool = null, mixed $startsAt = null, mixed $endsAt = null, array<string, mixed> $metadata = [])
 * @method static RatingSeason closeSeason(RatingSeason $season, bool $snapshot = true)
 * @method static int decayInactive(?string $pool = null, ?string $algorithm = null)
 * @method static Rating adjust(Model $model, float $amount, string $reason = 'manual_adjustment', ?string $pool = null, ?string $algorithm = null, ?int $seasonId = null, array<string, mixed> $metadata = [])
 * @method static Rating setRating(Model $model, float $value, string $reason = 'manual_set', ?string $pool = null, ?string $algorithm = null, ?int $seasonId = null, array<string, mixed> $metadata = [])
 * @method static Rating resetRating(Model $model, ?string $pool = null, ?string $algorithm = null, ?int $seasonId = null, string $reason = 'manual_reset')
 * @method static int|null rankOf(Model $model, ?string $pool = null, ?string $algorithm = null, ?int $seasonId = null, bool $includeProvisional = false, bool $conservative = false)
 * @method static Rating|null ratingForModel(Model $model, ?string $pool = null, ?string $algorithm = null, ?int $seasonId = null, bool $create = true)
 *
 * @see RatingKitManager
 */
class RatingKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RatingKitManager::class;
    }
}
