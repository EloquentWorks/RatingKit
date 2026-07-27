<?php

namespace EloquentWorks\RatingKit;

use EloquentWorks\RatingKit\Data\CompetitorInput;
use EloquentWorks\RatingKit\Data\MatchInput;
use EloquentWorks\RatingKit\Data\Participant;
use EloquentWorks\RatingKit\Data\RatingState;
use EloquentWorks\RatingKit\Data\RecordMatch;
use EloquentWorks\RatingKit\Data\Team;
use EloquentWorks\RatingKit\Data\TeamInput;
use EloquentWorks\RatingKit\Enums\MatchStatus;
use EloquentWorks\RatingKit\Events\LeaderboardSnapshotted;
use EloquentWorks\RatingKit\Events\MatchRated;
use EloquentWorks\RatingKit\Events\MatchVoided;
use EloquentWorks\RatingKit\Events\RatingAdjusted;
use EloquentWorks\RatingKit\Events\RatingUpdated;
use EloquentWorks\RatingKit\Events\SeasonClosed;
use EloquentWorks\RatingKit\Exceptions\InvalidMatch;
use EloquentWorks\RatingKit\Exceptions\UnsafeRollback;
use EloquentWorks\RatingKit\Models\LeaderboardSnapshot;
use EloquentWorks\RatingKit\Models\Rating;
use EloquentWorks\RatingKit\Models\RatingHistory;
use EloquentWorks\RatingKit\Models\RatingMatch;
use EloquentWorks\RatingKit\Models\RatingParticipant;
use EloquentWorks\RatingKit\Models\RatingSeason;
use EloquentWorks\RatingKit\Models\RatingTeam;
use EloquentWorks\RatingKit\Support\AlgorithmRegistry;
use EloquentWorks\RatingKit\Support\Math;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RatingKitManager
{
    /**
     * Create a new RatingKitManager instance.
     */
    public function __construct(protected AlgorithmRegistry $algorithms) {}

    /**
     * Get the algorithm registry.
     */
    public function algorithms(): AlgorithmRegistry
    {
        return $this->algorithms;
    }

    /**
     * Record and rate a match containing any number of teams and any number of players per team.
     */
    public function record(RecordMatch $request): RatingMatch
    {
        $algorithmKey = $request->algorithm ?? (string) config('rating-kit.default_algorithm', 'glicko2');
        $pool = $request->pool ?? (string) config('rating-kit.default_pool', 'default');
        $algorithm = $this->algorithms->resolve($algorithmKey);
        $teams = $this->normalizeTeams($request->teams);

        if (! $algorithm->supportsTeams() && $this->containsMultiPlayerTeam($teams)) {
            throw new InvalidMatch("The [{$algorithmKey}] algorithm does not support teams.");
        }

        if (! $algorithm->supportsMultipleTeams() && count($teams) > 2) {
            throw new InvalidMatch("The [{$algorithmKey}] algorithm does not support more than two teams.");
        }

        if ($request->externalId !== null) {
            $existing = $this->matchModel()::query()->where('external_id', $request->externalId)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        /** @var RatingMatch $match */
        $match = DB::transaction(function () use ($request, $algorithmKey, $pool, $algorithm, $teams): RatingMatch {
            $matchClass = $this->matchModel();
            $ratingClass = $this->ratingModel();
            $teamClass = $this->teamModel();
            $participantClass = $this->participantModel();
            $historyClass = $this->historyModel();

            /** @var RatingMatch $match */
            $match = $matchClass::query()->create([
                'uuid' => (string) Str::uuid(),
                'external_id' => $request->externalId,
                'pool' => $pool,
                'algorithm' => $algorithmKey,
                'algorithm_options' => $this->algorithms->options($algorithmKey),
                'season_id' => $request->seasonId,
                'status' => MatchStatus::Pending->value,
                'occurred_at' => $request->occurredAt ?? now(),
                'metadata' => $request->metadata,
            ]);

            /** @var array<string, Rating> $ratings */
            $ratings = [];
            $inputTeams = [];

            foreach ($teams as $team) {
                $competitors = [];

                foreach ($team->normalizedParticipants() as $participant) {
                    $key = $participant->key();
                    $rating = $this->findOrCreateRating(
                        $ratingClass,
                        $participant,
                        $pool,
                        $algorithmKey,
                        $request->seasonId,
                    );
                    $ratings[$key] = $rating;
                    $competitors[] = new CompetitorInput(
                        key: $key,
                        rating: new RatingState(
                            rating: $rating->rating,
                            deviation: $rating->deviation,
                            volatility: $rating->volatility,
                            games: $rating->games,
                            provisional: $rating->provisional,
                            metadata: $rating->metadata ?? [],
                        ),
                        weight: $participant->weight,
                        metadata: $participant->metadata,
                    );
                }

                $inputTeams[] = new TeamInput(
                    competitors: $competitors,
                    rank: $team->rank,
                    score: $team->score,
                    name: $team->name,
                    metadata: $team->metadata,
                );
            }

            $batch = $algorithm->rate(new MatchInput($inputTeams, metadata: $request->metadata));
            $minimumRank = min(array_map(static fn (Team $team): int => $team->rank, $teams));
            $firstPlaceCount = count(array_filter($teams, static fn (Team $team): bool => $team->rank === $minimumRank));

            foreach ($teams as $teamPosition => $team) {
                /** @var RatingTeam $storedTeam */
                $storedTeam = $teamClass::query()->create([
                    'match_id' => $match->getKey(),
                    'position' => $teamPosition + 1,
                    'rank' => $team->rank,
                    'score' => $team->score,
                    'name' => $team->name,
                    'metadata' => $team->metadata,
                ]);

                $outcome = $this->outcomeFor($team, $teams, $minimumRank, $firstPlaceCount);

                foreach ($team->normalizedParticipants() as $participant) {
                    $key = $participant->key();
                    $rating = $ratings[$key];
                    $change = $batch->get($key);
                    $games = $rating->games + 1;
                    $stats = $this->updatedStats($rating, $outcome);
                    $beforeRating = $rating->rating;

                    $rating->forceFill([
                        'rating' => $change->after->rating,
                        'deviation' => $change->after->deviation,
                        'volatility' => $change->after->volatility,
                        'games' => $games,
                        'wins' => $stats['wins'],
                        'draws' => $stats['draws'],
                        'losses' => $stats['losses'],
                        'streak' => $stats['streak'],
                        'provisional' => $games < (int) config('rating-kit.provisional_games', 10),
                        'last_competed_at' => $match->occurred_at,
                    ])->save();

                    /** @var RatingParticipant $storedParticipant */
                    $storedParticipant = $participantClass::query()->create([
                        'match_id' => $match->getKey(),
                        'team_id' => $storedTeam->getKey(),
                        'rating_id' => $rating->getKey(),
                        'rateable_type' => $participant->rateable->getMorphClass(),
                        'rateable_id' => $participant->rateable->getKey(),
                        'outcome' => $outcome,
                        'weight' => $participant->weight,
                        'rating_before' => $change->before->rating,
                        'rating_after' => $change->after->rating,
                        'rating_delta' => $change->delta(),
                        'deviation_before' => $change->before->deviation,
                        'deviation_after' => $change->after->deviation,
                        'volatility_before' => $change->before->volatility,
                        'volatility_after' => $change->after->volatility,
                        'metadata' => array_merge($participant->metadata, $change->metadata),
                    ]);

                    if ((bool) config('rating-kit.history_enabled', true)) {
                        $historyClass::query()->create([
                            'rating_id' => $rating->getKey(),
                            'match_id' => $match->getKey(),
                            'reason' => 'match',
                            'rating_before' => $change->before->rating,
                            'rating_after' => $change->after->rating,
                            'deviation_before' => $change->before->deviation,
                            'deviation_after' => $change->after->deviation,
                            'volatility_before' => $change->before->volatility,
                            'volatility_after' => $change->after->volatility,
                            'metadata' => [
                                'participant_id' => $storedParticipant->getKey(),
                                'outcome' => $outcome,
                            ],
                        ]);
                    }

                    $this->afterCommit(new RatingUpdated($rating, $match, $beforeRating, $rating->rating));
                }
            }

            $match->forceFill([
                'status' => MatchStatus::Processed,
                'processed_at' => now(),
            ])->save();

            $this->afterCommit(new MatchRated($match));

            return $match->load(['teams.participants', 'participants']);
        }, 3);

        return $match;
    }

    /**
     * Convenience wrapper for one player against another.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function oneVsOne(
        Model $left,
        Model $right,
        string $result = 'left',
        ?string $algorithm = null,
        ?string $pool = null,
        ?int $seasonId = null,
        ?string $externalId = null,
        array $metadata = [],
    ): RatingMatch {
        [$leftRank, $rightRank] = match ($result) {
            'left', 'win' => [1, 2],
            'right', 'loss' => [2, 1],
            'draw', 'tie' => [1, 1],
            default => throw new InvalidMatch('The one-vs-one result must be left, right, or draw.'),
        };

        return $this->record(new RecordMatch(
            teams: [
                new Team([$left], $leftRank),
                new Team([$right], $rightRank),
            ],
            algorithm: $algorithm,
            pool: $pool,
            seasonId: $seasonId,
            externalId: $externalId,
            metadata: $metadata,
        ));
    }

    /**
     * Convenience wrapper for any N-player team against another N-player team.
     *
     * @param  list<Model|Participant>  $left
     * @param  list<Model|Participant>  $right
     * @param  array<string, mixed>  $metadata
     */
    public function teamVsTeam(
        array $left,
        array $right,
        string $result = 'left',
        ?string $algorithm = null,
        ?string $pool = null,
        ?int $seasonId = null,
        array $metadata = [],
    ): RatingMatch {
        [$leftRank, $rightRank] = match ($result) {
            'left', 'win' => [1, 2],
            'right', 'loss' => [2, 1],
            'draw', 'tie' => [1, 1],
            default => throw new InvalidMatch('The team result must be left, right, or draw.'),
        };

        return $this->record(new RecordMatch(
            teams: [
                new Team($left, $leftRank, name: 'left'),
                new Team($right, $rightRank, name: 'right'),
            ],
            algorithm: $algorithm,
            pool: $pool,
            seasonId: $seasonId,
            metadata: $metadata,
        ));
    }

    /**
     * Rate a free-for-all or ordered multiplayer result.
     *
     * @param  list<Model|Participant|array{participant: Model|Participant, rank: int, score?: float|null}>  $placements
     * @param  array<string, mixed>  $metadata
     */
    public function freeForAll(
        array $placements,
        ?string $algorithm = null,
        ?string $pool = null,
        ?int $seasonId = null,
        array $metadata = [],
    ): RatingMatch {
        $teams = [];

        foreach ($placements as $index => $placement) {
            if (is_array($placement)) {
                $teams[] = new Team(
                    [$placement['participant']],
                    $placement['rank'],
                    $placement['score'] ?? null,
                );
            } else {
                $teams[] = new Team([$placement], $index + 1);
            }
        }

        return $this->record(new RecordMatch($teams, $algorithm, $pool, $seasonId, metadata: $metadata));
    }

    /**
     * @return Collection<int, array{rank: int, rating: Rating, score: float}>
     */
    public function leaderboard(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        int $limit = 100,
        bool $includeProvisional = false,
        bool $conservative = false,
    ): Collection {
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $direction = (string) config("rating-kit.algorithm_directions.{$algorithm}", 'desc');
        $scoreExpression = $conservative
            ? ($direction === 'asc' ? 'rating + (2 * deviation)' : 'rating - (2 * deviation)')
            : 'rating';

        $query = $this->ratingModel()::query()
            ->with('rateable')
            ->where('pool', $pool)
            ->where('algorithm', $algorithm)
            ->where('season_key', $seasonId ?? 0);

        if (! $includeProvisional) {
            $query->where('provisional', false);
        }

        $ratings = $query
            ->orderByRaw($scoreExpression.' '.($direction === 'asc' ? 'ASC' : 'DESC'))
            ->limit(max(1, $limit))
            ->get()
            ->values();

        $lastScore = null;
        $lastRank = 0;

        return $ratings->map(function (Rating $rating, int $index) use ($conservative, &$lastScore, &$lastRank): array {
            $score = $conservative
                ? ((string) config("rating-kit.algorithm_directions.{$rating->algorithm}", 'desc') === 'asc'
                    ? $rating->rating + 2.0 * $rating->deviation
                    : $rating->rating - 2.0 * $rating->deviation)
                : $rating->rating;

            if ($lastScore === null || abs($score - $lastScore) > 0.000001) {
                $lastRank = $index + 1;
                $lastScore = $score;
            }

            return [
                'rank' => $lastRank,
                'rating' => $rating,
                'score' => $score,
            ];
        });
    }

    /**
     * Return normalized estimated winning shares for two or more teams.
     *
     * @param  list<Team>  $teams
     * @return list<array{team: int, rating: float, probability: float}>
     */
    public function predict(array $teams, ?string $pool = null, ?string $algorithm = null, ?int $seasonId = null): array
    {
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $strengths = [];
        $ratings = [];

        foreach ($this->normalizeTeams($teams) as $index => $team) {
            $participants = $team->normalizedParticipants();
            $teamRating = array_sum(array_map(function (Participant $participant) use ($pool, $algorithm, $seasonId): float {
                $rating = $this->ratingForModel($participant->rateable, $pool, $algorithm, $seasonId, false);

                return $rating?->rating ?? (float) config('rating-kit.initial.rating', 1500.0);
            }, $participants)) / count($participants);
            $ratings[$index] = $teamRating;
            $direction = (string) config("rating-kit.algorithm_directions.{$algorithm}", 'desc');
            $initial = (float) config('rating-kit.initial.rating', 1500.0);
            $options = $this->algorithms->options($algorithm);
            $scale = max(0.000001, (float) ($options['scale'] ?? 400.0));
            $exponent = $direction === 'asc'
                ? ($initial - $teamRating) / $scale
                : ($teamRating - $initial) / $scale;
            $strengths[$index] = exp(max(-700.0, min(700.0, $exponent)));
        }

        $total = max(0.000001, array_sum($strengths));
        $result = [];

        foreach ($strengths as $index => $strength) {
            $result[] = [
                'team' => $index + 1,
                'rating' => $ratings[$index],
                'probability' => $strength / $total,
            ];
        }

        return $result;
    }

    /**
     * Return a normalized match-balance score from 0.0 to 1.0.
     *
     * @param  list<Team>  $teams
     */
    public function matchQuality(
        array $teams,
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): float {
        $probabilities = array_column($this->predict($teams, $pool, $algorithm, $seasonId), 'probability');
        $teamCount = count($probabilities);
        $ideal = 1.0 / $teamCount;
        $distance = array_sum(array_map(
            static fn (float $probability): float => abs($probability - $ideal),
            $probabilities,
        ));
        $maximumDistance = 2.0 * (1.0 - $ideal);

        return max(0.0, min(1.0, 1.0 - ($distance / max(0.000001, $maximumDistance))));
    }

    /**
     * Summarize one rating pool without loading rateable models.
     *
     * @return array{count: int, established: int, provisional: int, average: float|null, minimum: float|null, maximum: float|null, median: float|null}
     */
    public function poolStatistics(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): array {
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $ratings = $this->ratingModel()::query()
            ->where('pool', $pool)
            ->where('algorithm', $algorithm)
            ->where('season_key', $seasonId ?? 0)
            ->get(['rating', 'provisional']);
        $count = $ratings->count();

        if ($count === 0) {
            return [
                'count' => 0,
                'established' => 0,
                'provisional' => 0,
                'average' => null,
                'minimum' => null,
                'maximum' => null,
                'median' => null,
            ];
        }

        /** @var list<float> $values */
        $values = $ratings->pluck('rating')->map(static fn (mixed $value): float => (float) $value)->sort()->values()->all();
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2.0;
        $provisional = $ratings->where('provisional', true)->count();

        return [
            'count' => $count,
            'established' => $count - $provisional,
            'provisional' => $provisional,
            'average' => array_sum($values) / $count,
            'minimum' => $values[0],
            'maximum' => $values[$count - 1],
            'median' => $median,
        ];
    }

    public function void(RatingMatch $match, ?string $reason = null): RatingMatch
    {
        if (! $match->isProcessed()) {
            throw new UnsafeRollback('Only processed matches can be voided.');
        }

        return DB::transaction(function () use ($match, $reason): RatingMatch {
            $participants = $match->participants()->orderByDesc('id')->get();

            foreach ($participants as $participant) {
                $hasLaterResult = $this->participantModel()::query()
                    ->where('rating_id', $participant->rating_id)
                    ->where('id', '>', $participant->id)
                    ->exists();

                if ($hasLaterResult) {
                    throw new UnsafeRollback(
                        'This match is not the latest result for every participant. Rebuild the pool before voiding it.',
                    );
                }
            }

            foreach ($participants as $participant) {
                /** @var Rating $rating */
                $rating = $this->ratingModel()::query()->lockForUpdate()->findOrFail($participant->rating_id);
                $before = $rating->rating;
                $stats = $this->revertedStats($rating, $participant->outcome);

                $rating->forceFill([
                    'rating' => $participant->rating_before,
                    'deviation' => $participant->deviation_before,
                    'volatility' => $participant->volatility_before,
                    'games' => max(0, $rating->games - 1),
                    'wins' => $stats['wins'],
                    'draws' => $stats['draws'],
                    'losses' => $stats['losses'],
                    'streak' => 0,
                    'provisional' => max(0, $rating->games - 1) < (int) config('rating-kit.provisional_games', 10),
                ])->save();

                if ((bool) config('rating-kit.history_enabled', true)) {
                    $this->historyModel()::query()->create([
                        'rating_id' => $rating->getKey(),
                        'match_id' => $match->getKey(),
                        'reason' => 'match_voided',
                        'rating_before' => $before,
                        'rating_after' => $rating->rating,
                        'deviation_before' => $participant->deviation_after,
                        'deviation_after' => $rating->deviation,
                        'volatility_before' => $participant->volatility_after,
                        'volatility_after' => $rating->volatility,
                        'metadata' => ['reason' => $reason],
                    ]);
                }
            }

            $match->forceFill([
                'status' => MatchStatus::Voided,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            $this->afterCommit(new MatchVoided($match, $reason));

            return $match->fresh(['teams.participants', 'participants']) ?? $match;
        }, 3);
    }

    public function snapshotLeaderboard(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        int $limit = 100,
    ): LeaderboardSnapshot {
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $entries = $this->leaderboard($pool, $algorithm, $seasonId, $limit, true)
            ->map(static fn (array $entry): array => [
                'rank' => $entry['rank'],
                'rating_id' => $entry['rating']->getKey(),
                'rateable_type' => $entry['rating']->rateable_type,
                'rateable_id' => $entry['rating']->rateable_id,
                'rating' => $entry['rating']->rating,
                'deviation' => $entry['rating']->deviation,
                'games' => $entry['rating']->games,
                'provisional' => $entry['rating']->provisional,
            ])
            ->all();

        /** @var LeaderboardSnapshot $snapshot */
        $snapshot = $this->snapshotModel()::query()->create([
            'pool' => $pool,
            'algorithm' => $algorithm,
            'season_id' => $seasonId,
            'captured_at' => now(),
            'entry_count' => count($entries),
            'entries' => $entries,
        ]);

        $this->afterCommit(new LeaderboardSnapshotted($snapshot));

        return $snapshot;
    }

    /** @param array<string, mixed> $metadata */
    public function createSeason(
        string $name,
        string $slug,
        ?string $pool = null,
        mixed $startsAt = null,
        mixed $endsAt = null,
        array $metadata = [],
    ): RatingSeason {
        /** @var RatingSeason $season */
        $season = $this->seasonModel()::query()->create([
            'name' => $name,
            'slug' => $slug,
            'pool' => $pool ?? (string) config('rating-kit.default_pool', 'default'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'metadata' => $metadata,
        ]);

        return $season;
    }

    public function closeSeason(RatingSeason $season, bool $snapshot = true): RatingSeason
    {
        if ($season->closed_at !== null) {
            return $season;
        }

        $season->forceFill(['closed_at' => now()])->save();

        if ($snapshot) {
            /** @var list<string> $algorithms */
            $algorithms = $this->ratingModel()::query()
                ->where('pool', $season->pool)
                ->where('season_key', $season->getKey())
                ->distinct()
                ->orderBy('algorithm')
                ->pluck('algorithm')
                ->map(static fn (mixed $value): string => (string) $value)
                ->values()
                ->all();

            if ($algorithms === []) {
                $algorithms[] = (string) config('rating-kit.default_algorithm', 'glicko2');
            }

            foreach ($algorithms as $algorithm) {
                $this->snapshotLeaderboard($season->pool, $algorithm, $season->getKey());
            }
        }

        $this->afterCommit(new SeasonClosed($season));

        return $season;
    }

    public function decayInactive(?string $pool = null, ?string $algorithm = null): int
    {
        if (! (bool) config('rating-kit.decay.enabled', false)) {
            return 0;
        }

        $days = max(1, (int) config('rating-kit.decay.inactive_after_days', 90));
        $periodDays = max(1, (int) config('rating-kit.decay.period_days', 30));
        $points = max(0.0, (float) config('rating-kit.decay.points_per_period', 10.0));
        $minimum = (float) config('rating-kit.decay.minimum_rating', 1200.0);
        $maximum = (float) config('rating-kit.decay.maximum_rating', 2200.0);
        $count = 0;

        $query = $this->ratingModel()::query()
            ->where('last_competed_at', '<=', now()->subDays($days))
            ->whereRaw('(decayed_at IS NULL OR decayed_at <= ?)', [now()->subDays($periodDays)]);

        if ($pool !== null) {
            $query->where('pool', $pool);
        }

        if ($algorithm !== null) {
            $query->where('algorithm', $algorithm);
        }

        $query->chunkById(100, function (Collection $ratings) use (&$count, $points, $minimum, $maximum): void {
            /** @var Collection<int, Rating> $ratings */
            foreach ($ratings as $rating) {
                /** @var Rating $rating */
                $before = $rating->rating;
                $direction = (string) config("rating-kit.algorithm_directions.{$rating->algorithm}", 'desc');
                $after = $direction === 'asc'
                    ? min($maximum, $before + $points)
                    : max($minimum, $before - $points);

                if (abs($after - $before) <= 0.000001) {
                    continue;
                }

                $rating->forceFill(['rating' => $after, 'decayed_at' => now()])->save();
                $count++;

                if ((bool) config('rating-kit.history_enabled', true)) {
                    $this->historyModel()::query()->create([
                        'rating_id' => $rating->getKey(),
                        'match_id' => null,
                        'reason' => 'inactivity_decay',
                        'rating_before' => $before,
                        'rating_after' => $after,
                        'deviation_before' => $rating->deviation,
                        'deviation_after' => $rating->deviation,
                        'volatility_before' => $rating->volatility,
                        'volatility_after' => $rating->volatility,
                    ]);
                }
            }
        });

        return $count;
    }

    /** @param array<string, mixed> $metadata */
    public function adjust(
        Model $model,
        float $amount,
        string $reason = 'manual_adjustment',
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        array $metadata = [],
    ): Rating {
        $rating = $this->ratingForModel($model, $pool, $algorithm, $seasonId, true);

        if ($rating === null) {
            throw new \RuntimeException('Unable to create a rating record for manual adjustment.');
        }

        return $this->setRating(
            $model,
            $rating->rating + $amount,
            $reason,
            $pool,
            $algorithm,
            $seasonId,
            $metadata,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function setRating(
        Model $model,
        float $value,
        string $reason = 'manual_set',
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        array $metadata = [],
    ): Rating {
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');

        return DB::transaction(function () use ($model, $value, $reason, $pool, $algorithm, $seasonId, $metadata): Rating {
            $rating = $this->ratingForModel($model, $pool, $algorithm, $seasonId, true);

            if ($rating === null) {
                throw new \RuntimeException('Unable to create a rating record for manual adjustment.');
            }

            /** @var Rating $rating */
            $rating = $this->ratingModel()::query()->lockForUpdate()->findOrFail($rating->getKey());
            $before = $rating->rating;
            $minimum = config('rating-kit.rating_floor');
            $maximum = config('rating-kit.rating_ceiling');
            $after = Math::clamp(
                $value,
                $minimum !== null ? (float) $minimum : null,
                $maximum !== null ? (float) $maximum : null,
            );

            $rating->forceFill(['rating' => $after])->save();

            if ((bool) config('rating-kit.history_enabled', true)) {
                $this->historyModel()::query()->create([
                    'rating_id' => $rating->getKey(),
                    'match_id' => null,
                    'reason' => $reason,
                    'rating_before' => $before,
                    'rating_after' => $after,
                    'deviation_before' => $rating->deviation,
                    'deviation_after' => $rating->deviation,
                    'volatility_before' => $rating->volatility,
                    'volatility_after' => $rating->volatility,
                    'metadata' => $metadata,
                ]);
            }

            $this->afterCommit(new RatingAdjusted($rating, $before, $after, $reason));

            return $rating;
        }, 3);
    }

    public function resetRating(
        Model $model,
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        string $reason = 'manual_reset',
    ): Rating {
        return $this->setRating(
            $model,
            (float) config('rating-kit.initial.rating', 1500.0),
            $reason,
            $pool,
            $algorithm,
            $seasonId,
        );
    }

    public function rankOf(
        Model $model,
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        bool $includeProvisional = false,
        bool $conservative = false,
    ): ?int {
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $rating = $this->ratingForModel($model, $pool, $algorithm, $seasonId, false);

        if ($rating === null || (! $includeProvisional && $rating->provisional)) {
            return null;
        }

        $query = $this->ratingModel()::query()
            ->where('pool', $pool)
            ->where('algorithm', $algorithm)
            ->where('season_key', $seasonId ?? 0);

        if (! $includeProvisional) {
            $query->where('provisional', false);
        }

        $direction = (string) config("rating-kit.algorithm_directions.{$algorithm}", 'desc');

        if ($conservative) {
            if ($direction === 'asc') {
                $score = $rating->rating + 2.0 * $rating->deviation;
                $query->whereRaw('(rating + (2 * deviation)) < ?', [$score]);
            } else {
                $score = $rating->rating - 2.0 * $rating->deviation;
                $query->whereRaw('(rating - (2 * deviation)) > ?', [$score]);
            }
        } else {
            $query->where('rating', $direction === 'asc' ? '<' : '>', $rating->rating);
        }

        return $query->count() + 1;
    }

    public function ratingForModel(
        Model $model,
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        bool $create = true,
    ): ?Rating {
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');

        $query = $this->ratingModel()::query()
            ->where('rateable_type', $model->getMorphClass())
            ->where('rateable_id', $model->getKey())
            ->where('pool', $pool)
            ->where('algorithm', $algorithm)
            ->where('season_key', $seasonId ?? 0);
        $rating = $query->first();

        if ($rating !== null || ! $create) {
            return $rating;
        }

        return $this->findOrCreateRating(
            $this->ratingModel(),
            new Participant($model),
            $pool,
            $algorithm,
            $seasonId,
        );
    }

    /**
     * @param  list<Team>  $teams
     * @return list<Team>
     */
    protected function normalizeTeams(array $teams): array
    {
        if (count($teams) < 2) {
            throw new InvalidMatch('A rated match must contain at least two teams.');
        }

        $seen = [];

        foreach ($teams as $team) {
            if (! $team instanceof Team) {
                throw new InvalidMatch('Every match entry must be a RatingKit Team data object.');
            }

            foreach ($team->normalizedParticipants() as $participant) {
                $key = $participant->key();

                if (isset($seen[$key])) {
                    throw new InvalidMatch("Participant [{$key}] appears more than once in the same match.");
                }

                $seen[$key] = true;
            }
        }

        return array_values($teams);
    }

    /** @param list<Team> $teams */
    protected function containsMultiPlayerTeam(array $teams): bool
    {
        foreach ($teams as $team) {
            if (count($team->participants) > 1) {
                return true;
            }
        }

        return false;
    }

    /** @param class-string<Rating> $ratingClass */
    protected function findOrCreateRating(
        string $ratingClass,
        Participant $participant,
        string $pool,
        string $algorithm,
        ?int $seasonId,
    ): Rating {
        /** @var Rating $rating */
        $rating = $ratingClass::query()->firstOrCreate(
            [
                'rateable_type' => $participant->rateable->getMorphClass(),
                'rateable_id' => $participant->rateable->getKey(),
                'pool' => $pool,
                'algorithm' => $algorithm,
                'season_key' => $seasonId ?? 0,
            ],
            [
                'season_id' => $seasonId,
                'rating' => (float) config('rating-kit.initial.rating', 1500.0),
                'deviation' => (float) config('rating-kit.initial.deviation', 350.0),
                'volatility' => (float) config('rating-kit.initial.volatility', 0.06),
                'provisional' => true,
            ],
        );

        /** @var Rating $locked */
        $locked = $ratingClass::query()->lockForUpdate()->findOrFail($rating->getKey());

        return $locked;
    }

    /**
     * @param  list<Team>  $teams
     */
    protected function outcomeFor(Team $team, array $teams, int $minimumRank, int $firstPlaceCount): string
    {
        if (count(array_unique(array_map(static fn (Team $entry): int => $entry->rank, $teams))) === 1) {
            return 'draw';
        }

        if ($team->rank === $minimumRank) {
            return $firstPlaceCount > 1 ? 'draw' : 'win';
        }

        return 'loss';
    }

    /** @return array{wins: int, draws: int, losses: int, streak: int} */
    protected function updatedStats(Rating $rating, string $outcome): array
    {
        return [
            'wins' => $rating->wins + ($outcome === 'win' ? 1 : 0),
            'draws' => $rating->draws + ($outcome === 'draw' ? 1 : 0),
            'losses' => $rating->losses + ($outcome === 'loss' ? 1 : 0),
            'streak' => match ($outcome) {
                'win' => $rating->streak >= 0 ? $rating->streak + 1 : 1,
                'loss' => $rating->streak <= 0 ? $rating->streak - 1 : -1,
                default => 0,
            },
        ];
    }

    /** @return array{wins: int, draws: int, losses: int} */
    protected function revertedStats(Rating $rating, string $outcome): array
    {
        return [
            'wins' => max(0, $rating->wins - ($outcome === 'win' ? 1 : 0)),
            'draws' => max(0, $rating->draws - ($outcome === 'draw' ? 1 : 0)),
            'losses' => max(0, $rating->losses - ($outcome === 'loss' ? 1 : 0)),
        ];
    }

    protected function afterCommit(object $event): void
    {
        if (! (bool) config('rating-kit.dispatch_events', true)) {
            return;
        }

        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit(static fn () => event($event));

            return;
        }

        event($event);
    }

    /** @return class-string<Rating> */
    protected function ratingModel(): string
    {
        /** @var class-string<Rating> $model */
        $model = config('rating-kit.models.rating', Rating::class);

        return $model;
    }

    /** @return class-string<RatingMatch> */
    protected function matchModel(): string
    {
        /** @var class-string<RatingMatch> $model */
        $model = config('rating-kit.models.match', RatingMatch::class);

        return $model;
    }

    /** @return class-string<RatingTeam> */
    protected function teamModel(): string
    {
        /** @var class-string<RatingTeam> $model */
        $model = config('rating-kit.models.team', RatingTeam::class);

        return $model;
    }

    /** @return class-string<RatingParticipant> */
    protected function participantModel(): string
    {
        /** @var class-string<RatingParticipant> $model */
        $model = config('rating-kit.models.participant', RatingParticipant::class);

        return $model;
    }

    /** @return class-string<RatingHistory> */
    protected function historyModel(): string
    {
        /** @var class-string<RatingHistory> $model */
        $model = config('rating-kit.models.history', RatingHistory::class);

        return $model;
    }

    /** @return class-string<RatingSeason> */
    protected function seasonModel(): string
    {
        /** @var class-string<RatingSeason> $model */
        $model = config('rating-kit.models.season', RatingSeason::class);

        return $model;
    }

    /** @return class-string<LeaderboardSnapshot> */
    protected function snapshotModel(): string
    {
        /** @var class-string<LeaderboardSnapshot> $model */
        $model = config('rating-kit.models.leaderboard_snapshot', LeaderboardSnapshot::class);

        return $model;
    }
}
