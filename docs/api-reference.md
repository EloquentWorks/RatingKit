# 📖 API Reference

## 🏆 `RatingKitManager`

### Match recording

```php
record(RecordMatch $request): RatingMatch
oneVsOne(Model $left, Model $right, string $result = 'left', ...): RatingMatch
teamVsTeam(array $left, array $right, string $result = 'left', ...): RatingMatch
freeForAll(array $placements, ...): RatingMatch
```

### Reading and prediction

```php
leaderboard(...): Collection
rankOf(Model $model, ...): ?int
predict(array $teams, ...): array
matchQuality(array $teams, ...): float
poolStatistics(...): array
ratingForModel(Model $model, ..., bool $create = true): ?Rating
```

### Administration

```php
void(RatingMatch $match, ?string $reason = null): RatingMatch
adjust(Model $model, float $amount, string $reason = 'manual_adjustment', ...): Rating
setRating(Model $model, float $value, string $reason = 'manual_set', ...): Rating
resetRating(Model $model, ...): Rating
decayInactive(?string $pool = null, ?string $algorithm = null): int
```

### Seasons and snapshots

```php
createSeason(...): RatingSeason
closeSeason(RatingSeason $season, bool $snapshot = true): RatingSeason
snapshotLeaderboard(...): LeaderboardSnapshot
```

### Algorithms

```php
algorithms(): AlgorithmRegistry
```

## 🧬 `HasRatings`

### Relationships and rating identities

```php
ratings(): MorphMany
ratingParticipations(): MorphMany
ratingFor(..., bool $create = true): ?Rating
hasRating(...): bool
ratingsForPool(string $pool, ?int $seasonId = null): Collection
ratingsUsingAlgorithm(string $algorithm, ?int $seasonId = null): Collection
seasonRatings(?int $seasonId): Collection
ratingPools(): Collection
ratingAlgorithms(?string $pool = null): Collection
```

### Current values and statistics

```php
currentRating(...): float
ratingDeviation(...): float
ratingVolatility(...): float
conservativeRating(..., float $deviationMultiplier = 2.0): float
ratingStats(...): array
ratedGames(...): int
ratingWins(...): int
ratingDraws(...): int
ratingLosses(...): int
ratingWinRate(...): float
ratingStreak(...): int
isProvisional(...): bool
hasEstablishedRating(...): bool
ratingRank(...): ?int
```

### History and matches

```php
ratingHistory(..., ?int $limit = null): Collection
latestRatingChange(...): ?RatingHistory
peakRating(...): float
recentRatingParticipations(...): Collection
latestRatingParticipation(...): ?RatingParticipant
ratedMatches(): Builder
recentRatedMatches(int $limit = 20): Collection
```

### Administration

```php
adjustRating(float $amount, string $reason = 'manual_adjustment', ...): Rating
setRating(float $value, string $reason = 'manual_set', ...): Rating
resetRating(...): Rating
```

See the complete [HasRatings guide](has-ratings.md).

## 📦 Data Objects

- `RecordMatch`
- `Team`
- `Participant`
- `MatchInput`
- `TeamInput`
- `CompetitorInput`
- `RatingState`
- `RatingBatch`
- `RatingChange`

Application-facing match recording normally uses `RecordMatch`, `Team`, and `Participant`. Algorithm implementations use the immutable input and output objects.
