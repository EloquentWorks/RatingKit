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

/**
 * The RatingKitManager class is responsible for managing player ratings, recording matches, and providing
 * leaderboard and prediction functionalities.
 */
class RatingKitManager
{
    /**
     * Create a new RatingKitManager instance.
     *
     * @param  AlgorithmRegistry  $algorithms The algorithm registry instance
     * @return void
     */
    public function __construct(protected AlgorithmRegistry $algorithms) {}

    /**
     * Get the algorithm registry instance.
     *
     * @return AlgorithmRegistry The algorithm registry instance.
     */
    public function algorithms(): AlgorithmRegistry
    {
        return $this->algorithms;
    }

    /**
     * Record a match and update player ratings accordingly.
     *
     * @param  RecordMatch  $request The request containing match details and teams
     * @return RatingMatch The recorded match instance with updated ratings
     * @throws InvalidMatch If the match is invalid based on the algorithm's capabilities
     */
    public function record(RecordMatch $request): RatingMatch
    {
        // Determine the algorithm and pool to use for rating the match, falling back to default values if not provided
        $algorithmKey = $request->algorithm ?? (string) config('rating-kit.default_algorithm', 'glicko2');
        $pool = $request->pool ?? (string) config('rating-kit.default_pool', 'default');
        $algorithm = $this->algorithms->resolve($algorithmKey);
        $teams = $this->normalizeTeams($request->teams);

        // Check if the algorithm supports teams and if any team has more than one participant
        if (! $algorithm->supportsTeams() && $this->containsMultiPlayerTeam($teams)) {
            throw new InvalidMatch("The [{$algorithmKey}] algorithm does not support teams.");
        }

        // Check if the algorithm supports multiple teams and if the number of teams exceeds two
        if (! $algorithm->supportsMultipleTeams() && count($teams) > 2) {
            throw new InvalidMatch("The [{$algorithmKey}] algorithm does not support more than two teams.");
        }

        // Check if an external ID is provided and if a match with the same external ID already exists
        if ($request->externalId !== null) {
            $existing = $this->matchModel()::query()->where('external_id', $request->externalId)->first();

            // If a match with the same external ID already exists, return the existing match instead of creating a new one
            if ($existing !== null) {
                return $existing;
            }
        }

        /** @var RatingMatch $match */
        $match = DB::transaction(function () use ($request, $algorithmKey, $pool, $algorithm, $teams): RatingMatch {
            // Create the match record in the database with the provided details
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

            // Loop through each team in the match and prepare their competitors and ratings
            foreach ($teams as $team) {
                $competitors = [];

                // Loop through each participant in the team and prepare their rating and competitor input
                foreach ($team->normalizedParticipants() as $participant) {
                    // Get the unique key for the participant and find or create their rating in the specified pool and algorithm
                    $key = $participant->key();
                    $rating = $this->findOrCreateRating(
                        $ratingClass,
                        $participant,
                        $pool,
                        $algorithmKey,
                        $request->seasonId,
                    );
                    $ratings[$key] = $rating;

                    // Create a CompetitorInput instance for the current participant, including their key, rating state, weight, and metadata
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

                // Create a TeamInput instance for the current team, including its competitors, rank, score, name, and metadata
                $inputTeams[] = new TeamInput(
                    competitors: $competitors,
                    rank: $team->rank,
                    score: $team->score,
                    name: $team->name,
                    metadata: $team->metadata,
                );
            }

            // Rate the match using the specified algorithm and input teams, producing a batch of updated ratings
            $batch = $algorithm->rate(new MatchInput($inputTeams, metadata: $request->metadata));
            $minimumRank = min(array_map(static fn (Team $team): int => $team->rank, $teams));
            $firstPlaceCount = count(array_filter($teams, static fn (Team $team): bool => $team->rank === $minimumRank));

            // Store each team and its participants in the database, updating their ratings and creating corresponding records
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

                // Determine the outcome for the team based on its rank and the ranks of other teams
                $outcome = $this->outcomeFor($team, $teams, $minimumRank, $firstPlaceCount);

                // Update each participant's rating and create a corresponding RatingParticipant record
                foreach ($team->normalizedParticipants() as $participant) {
                    // Get the unique key for the participant and retrieve their current rating
                    $key = $participant->key();
                    $rating = $ratings[$key];
                    $change = $batch->get($key);
                    $games = $rating->games + 1;
                    $stats = $this->updatedStats($rating, $outcome);
                    $beforeRating = $rating->rating;

                    // Update the rating record with the new values after the match
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

                    // If history tracking is enabled in the configuration, create a new rating history record for the participant
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

                    // Dispatch the RatingUpdated event after the transaction is committed
                    $this->afterCommit(new RatingUpdated($rating, $match, $beforeRating, $rating->rating));
                }
            }

            // Dispatch the RatingAdjusted event after the transaction is committed
            $match->forceFill([
                'status' => MatchStatus::Processed,
                'processed_at' => now(),
            ])->save();

            // Dispatch the MatchRated event after the transaction is committed
            $this->afterCommit(new MatchRated($match));

            // Dispatch the LeaderboardSnapshotted event after the transaction is committed
            return $match->load(['teams.participants', 'participants']);
        }, 3);

        // Dispatch the LeaderboardSnapshotted event after the transaction is committed
        return $match;
    }

    /**
     * Convenience wrapper for one player against another.
     *
     * @param  Model  $left
     * @param  Model  $right
     * @param  string  $result
     * @param  string|null  $algorithm
     * @param  string|null  $pool
     * @param  int|null  $seasonId
     * @param  string|null  $externalId
     * @param  array<string, mixed>  $metadata
     * @return RatingMatch
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
        // Validate the result and determine the ranks for the left and right players
        [$leftRank, $rightRank] = match ($result) {
            'left', 'win' => [1, 2],
            'right', 'loss' => [2, 1],
            'draw', 'tie' => [1, 1],
            default => throw new InvalidMatch('The one-vs-one result must be left, right, or draw.'),
        };

        // Record the match using the provided players, result, and optional parameters
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
     * @param  string  $result
     * @param  string|null  $algorithm
     * @param  string|null  $pool
     * @param  int|null  $seasonId
     * @param  array<string, mixed>  $metadata
     * @return RatingMatch
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
        // Validate that both teams have at least one participant
        [$leftRank, $rightRank] = match ($result) {
            'left', 'win' => [1, 2],
            'right', 'loss' => [2, 1],
            'draw', 'tie' => [1, 1],
            default => throw new InvalidMatch('The team result must be left, right, or draw.'),
        };

        // Record the match using the provided teams, result, and optional parameters
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
     * Convenience wrapper for a free-for-all match with any number of players.
     *
     * @param  array<int, Model|Participant|array{participant: Model|Participant, rank: int, score?: float|null}>  $placements
     * @param  string|null  $algorithm
     * @param  string|null  $pool
     * @param  int|null  $seasonId
     * @param  array<string, mixed>  $metadata
     * @return RatingMatch
     */
    public function freeForAll(
        array $placements,
        ?string $algorithm = null,
        ?string $pool = null,
        ?int $seasonId = null,
        array $metadata = [],
    ): RatingMatch {
        $teams = [];

        // Normalize the placements into Team instances
        foreach ($placements as $index => $placement) {
            if (is_array($placement)) {
                // If the placement is an array, create a Team instance with the participant, rank, and optional score
                $teams[] = new Team(
                    [$placement['participant']],
                    $placement['rank'],
                    $placement['score'] ?? null,
                );
            } else {
                // If the placement is a single participant, create a Team instance with the participant and rank
                $teams[] = new Team([$placement], $index + 1);
            }
        }

        // Record the match using the normalized teams and provided parameters
        return $this->record(new RecordMatch($teams, $algorithm, $pool, $seasonId, metadata: $metadata));
    }

    /**
     * Get the current leaderboard for a given pool and algorithm.
     *
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @param  int  $limit
     * @param  bool  $includeProvisional
     * @param  bool  $conservative
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
        // Use the default pool and algorithm if not provided
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $direction = (string) config("rating-kit.algorithm_directions.{$algorithm}", 'desc');

        // Determine the score expression based on whether conservative ranking is enabled and the algorithm's direction
        $scoreExpression = $conservative
            ? ($direction === 'asc' ? 'rating + (2 * deviation)' : 'rating - (2 * deviation)')
            : 'rating';

        // Build the query to retrieve ratings for the specified pool, algorithm, and season
        $query = $this->ratingModel()::query()
            ->with('rateable')
            ->where('pool', $pool)
            ->where('algorithm', $algorithm)
            ->where('season_key', $seasonId ?? 0);

        // Exclude provisional ratings from the leaderboard if $includeProvisional is false
        if (! $includeProvisional) {
            $query->where('provisional', false);
        }

        // Execute the query to retrieve the ratings, ordered by the score expression and limited to the specified number of results
        $ratings = $query
            ->orderByRaw($scoreExpression.' '.($direction === 'asc' ? 'ASC' : 'DESC'))
            ->limit(max(1, $limit))
            ->get()
            ->values();

        // Initialize variables to track the last score and rank for ranking purposes
        $lastScore = null;
        $lastRank = 0;

        // Map the ratings to an array containing rank, rating, and score, while handling ties in ranking
        return $ratings->map(function (Rating $rating, int $index) use ($conservative, &$lastScore, &$lastRank): array {
            $score = $conservative
                ? ((string) config("rating-kit.algorithm_directions.{$rating->algorithm}", 'desc') === 'asc'
                    ? $rating->rating + 2.0 * $rating->deviation
                    : $rating->rating - 2.0 * $rating->deviation)
                : $rating->rating;

            // If the current score is different from the last score, update the rank and last score
            if ($lastScore === null || abs($score - $lastScore) > 0.000001) {
                $lastRank = $index + 1;
                $lastScore = $score;
            }

            // Return an array containing the rank, rating, and score for the current rating
            return [
                'rank' => $lastRank,
                'rating' => $rating,
                'score' => $score,
            ];
        });
    }

    /**
     * Predict the probability of each team winning based on their current ratings.
     *
     * @param  list<Team>  $teams
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @return array<int, array{team: int, rating: float, probability: float}>
     */
    public function predict(array $teams, ?string $pool = null, ?string $algorithm = null, ?int $seasonId = null): array
    {
        // Use the default pool and algorithm if not provided
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $strengths = [];
        $ratings = [];

        // Calculate the strength and rating for each team based on their participants' ratings
        foreach ($this->normalizeTeams($teams) as $index => $team) {
            $participants = $team->normalizedParticipants();

            // Calculate the average rating for the team by summing the ratings of all participants and dividing by the number of participants
            $teamRating = array_sum(array_map(function (Participant $participant) use ($pool, $algorithm, $seasonId): float {
                $rating = $this->ratingForModel($participant->rateable, $pool, $algorithm, $seasonId, false);

                // If the participant has a rating, return it; otherwise, return the initial rating from the configuration
                return $rating?->rating ?? (float) config('rating-kit.initial.rating', 1500.0);
            }, $participants)) / count($participants);

            // Store the calculated team rating and strength for each team in the respective arrays
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

        // Calculate the total strength of all teams, ensuring it is not zero to avoid division by zero
        $total = max(0.000001, array_sum($strengths));
        $result = [];

        // Calculate the probability for each team based on their strength relative to the total strength
        foreach ($strengths as $index => $strength) {
            $result[] = [
                'team' => $index + 1,
                'rating' => $ratings[$index],
                'probability' => $strength / $total,
            ];
        }

        // Sort the result by team index to maintain the original order of teams
        return $result;
    }

    /**
     * Return a normalized measure of how evenly matched the teams are.
     *
     * @param  list<Team>  $teams
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @return float 0.0 = very uneven, 1.0 = perfectly even
     */
    public function matchQuality(
        array $teams,
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): float {
        // Get the predicted probabilities for each team in the match
        $probabilities = array_column($this->predict($teams, $pool, $algorithm, $seasonId), 'probability');
        $teamCount = count($probabilities);
        $ideal = 1.0 / $teamCount;
        $distance = array_sum(array_map(
            static fn (float $probability): float => abs($probability - $ideal),
            $probabilities,
        ));

        // Calculate the maximum possible distance for the given number of teams
        $maximumDistance = 2.0 * (1.0 - $ideal);

        // Return a normalized measure of match quality, ensuring it is between 0.0 and 1.0
        return max(0.0, min(1.0, 1.0 - ($distance / max(0.000001, $maximumDistance))));
    }

    /**
     * Summarize one rating pool without loading rateable models.
     *
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @return array{count: int, established: int, provisional: int, average: float|null, minimum: float|null, maximum: float|null, median: float|null}
     */
    public function poolStatistics(
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
    ): array {
        // Use the default pool and algorithm if not provided
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $ratings = $this->ratingModel()::query()
            ->where('pool', $pool)
            ->where('algorithm', $algorithm)
            ->where('season_key', $seasonId ?? 0)
            ->get(['rating', 'provisional']);
        
        // Count the number of ratings in the specified pool, algorithm, and season
        $count = $ratings->count();

        // If there are no ratings, return default statistics with null values for average, minimum, maximum, and median
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

        // Return an array containing the calculated statistics for the specified pool, algorithm, and season
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

    /**
     * Void a previously processed match and revert all ratings to their previous state.
     *
     * @param  RatingMatch  $match The match instance to be voided
     * @param  string|null  $reason An optional reason for voiding the match
     * @throws UnsafeRollback if the match is not the latest result for every participant
     * @return RatingMatch The updated match instance after voiding
     */
    public function void(RatingMatch $match, ?string $reason = null): RatingMatch
    {
        // Check if the match has been processed before attempting to void it
        if (! $match->isProcessed()) {
            throw new UnsafeRollback('Only processed matches can be voided.');
        }

        // Use a database transaction to ensure that all changes are applied atomically
        return DB::transaction(function () use ($match, $reason): RatingMatch {
            $participants = $match->participants()->orderByDesc('id')->get();

            // Check if the match is the latest result for every participant before voiding
            foreach ($participants as $participant) {
                $hasLaterResult = $this->participantModel()::query()
                    ->where('rating_id', $participant->rating_id)
                    ->where('id', '>', $participant->id)
                    ->exists();

                // If there is a later result for any participant, throw an exception to prevent unsafe rollback
                if ($hasLaterResult) {
                    throw new UnsafeRollback(
                        'This match is not the latest result for every participant. Rebuild the pool before voiding it.',
                    );
                }
            }

            // Revert each participant's rating to its previous state and update the match status to "voided"
            foreach ($participants as $participant) {
                /** @var Rating $rating */
                $rating = $this->ratingModel()::query()->lockForUpdate()->findOrFail($participant->rating_id);
                $before = $rating->rating;
                $stats = $this->revertedStats($rating, $participant->outcome);

                // Update the rating with the previous values and save it
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

                // Trigger the RatingUpdated event to notify listeners of the rating change
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

            // Update the match status to "voided" and set the void reason and timestamp
            $match->forceFill([
                'status' => MatchStatus::Voided,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            // Trigger the MatchVoided event to notify listeners that the match has been voided
            $this->afterCommit(new MatchVoided($match, $reason));

            // Return the updated match instance with related teams and participants loaded
            return $match->fresh(['teams.participants', 'participants']) ?? $match;
        }, 3);
    }

    /**
     * Snapshot the current leaderboard for a given pool and algorithm.
     *
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @param  int  $limit
     * @return LeaderboardSnapshot
     */
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

    /**
     * Create a new rating season.
     *
     * @param  string  $name The name of the season.
     * @param  string  $slug A unique slug for the season.
     * @param  string|null  $pool The rating pool for the season (optional).
     * @param  mixed|null  $startsAt The start date of the season (optional).
     * @param  mixed|null  $endsAt The end date of the season (optional).
     * @param  array<string, mixed>  $metadata Additional metadata for the season (optional).
     * @return RatingSeason Returns the newly created RatingSeason instance.
     */
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

    /**
     * Close a season and optionally snapshot the leaderboard.
     *
     * @param  RatingSeason  $season
     * @param  bool  $snapshot Whether to snapshot the leaderboard after closing the season.
     * @return RatingSeason Returns the updated season instance.
     */
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

    /**
     * Decay ratings for inactive players based on the configured decay settings.
     *
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @return int Returns the number of ratings that were decayed.
     */
    public function decayInactive(?string $pool = null, ?string $algorithm = null): int
    {
        // Check if decay is enabled in the configuration; if not, return 0 to indicate no ratings were decayed
        if (! (bool) config('rating-kit.decay.enabled', false)) {
            return 0;
        }

        // Get the decay settings from the configuration, ensuring they are valid and non-negative
        $days = max(1, (int) config('rating-kit.decay.inactive_after_days', 90));
        $periodDays = max(1, (int) config('rating-kit.decay.period_days', 30));
        $points = max(0.0, (float) config('rating-kit.decay.points_per_period', 10.0));
        $minimum = (float) config('rating-kit.decay.minimum_rating', 1200.0);
        $maximum = (float) config('rating-kit.decay.maximum_rating', 2200.0);
        $count = 0;

        // Build a query to select ratings that have not competed within the specified number of days and have not been decayed recently
        $query = $this->ratingModel()::query()
            ->where('last_competed_at', '<=', now()->subDays($days))
            ->whereRaw('(decayed_at IS NULL OR decayed_at <= ?)', [now()->subDays($periodDays)]);

        // Filter the query by pool and algorithm if they are provided
        if ($pool !== null) {
            $query->where('pool', $pool);
        }

        // Filter the query by algorithm if it is provided
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

                // If the rating has not changed significantly, skip updating it
                if (abs($after - $before) <= 0.000001) {
                    continue;
                }

                // Update the rating with the new value and set the decayed_at timestamp to now
                $rating->forceFill(['rating' => $after, 'decayed_at' => now()])->save();
                $count++;

                // Record the decay in the history table if enabled
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

        // Notify listeners that ratings have been decayed due to inactivity
        return $count;
    }

    /**
     * Adjust a model's rating by a specific amount, clamped to the configured minimum and maximum if set.
     *
     * @param  Model  $model
     * @param  float  $amount
     * @param  string  $reason
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @param  array<string, mixed>  $metadata
     * @return Rating
     */
    public function adjust(
        Model $model,
        float $amount,
        string $reason = 'manual_adjustment',
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        array $metadata = [],
    ): Rating {
        // Get the pool and algorithm from the parameters or the configuration.
        $rating = $this->ratingForModel($model, $pool, $algorithm, $seasonId, true);

        // If the rating record could not be found or created, throw an exception.
        if ($rating === null) {
            throw new \RuntimeException('Unable to create a rating record for manual adjustment.');
        }

        // Adjust the rating by the specified amount.
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

    /**
     * Set a model's rating to a specific value, clamped to the configured minimum and maximum if set.
     *
     * @param  Model  $model
     * @param  float  $value
     * @param  string  $reason
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @param  array<string, mixed>  $metadata
     * @return Rating
     */
    public function setRating(
        Model $model,
        float $value,
        string $reason = 'manual_set',
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        array $metadata = [],
    ): Rating {
        // Get the pool and algorithm from the parameters or the configuration.
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');

        // Use a database transaction to ensure that the rating adjustment is atomic and consistent.
        return DB::transaction(function () use ($model, $value, $reason, $pool, $algorithm, $seasonId, $metadata): Rating {
            // Get or create the rating record for the model in the specified pool, algorithm, and season.
            $rating = $this->ratingForModel($model, $pool, $algorithm, $seasonId, true);

            // If the rating record could not be found or created, throw an exception.
            if ($rating === null) {
                throw new \RuntimeException('Unable to create a rating record for manual adjustment.');
            }

            /** @var Rating $rating */
            $rating = $this->ratingModel()::query()->lockForUpdate()->findOrFail($rating->getKey());
            $before = $rating->rating;
            $minimum = config('rating-kit.rating_floor');
            $maximum = config('rating-kit.rating_ceiling');

            // Clamp the new rating value to the configured minimum and maximum, if set.
            $after = Math::clamp(
                $value,
                $minimum !== null ? (float) $minimum : null,
                $maximum !== null ? (float) $maximum : null,
            );

            // Update the rating record with the new rating value.
            $rating->forceFill(['rating' => $after])->save();

            // Record the adjustment in the history table if enabled.
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

            // Notify listeners that a rating has been adjusted.
            $this->afterCommit(new RatingAdjusted($rating, $before, $after, $reason));

            // Return the updated rating record.
            return $rating;
        }, 3);
    }

    /**
     * Reset a model's rating to the initial value.
     *
     * @param  Model  $model
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @param  string  $reason
     * @return Rating
     */
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

    /**
     * Return the rank of a given model in a given pool and algorithm.
     *
     * @param  Model  $model
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @param  bool  $includeProvisional
     * @param  bool  $conservative
     * @return int|null
     */
    public function rankOf(
        Model $model,
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        bool $includeProvisional = false,
        bool $conservative = false,
    ): ?int {
        // Get the rating for the model in the specified pool, algorithm, and season.
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $rating = $this->ratingForModel($model, $pool, $algorithm, $seasonId, false);

        // If the model has no rating, or if provisional ratings are excluded and this rating is provisional, return null.
        if ($rating === null || (! $includeProvisional && $rating->provisional)) {
            return null;
        }

        // Build a query to count how many ratings are better than this one, according to the algorithm's
        // direction and whether provisional ratings should be included.
        $query = $this->ratingModel()::query()
            ->where('pool', $pool)
            ->where('algorithm', $algorithm)
            ->where('season_key', $seasonId ?? 0);

        // Exclude provisional ratings from the rank calculation if requested.
        if (! $includeProvisional) {
            $query->where('provisional', false);
        }

        // Determine the direction of the algorithm (ascending or descending).
        $direction = (string) config("rating-kit.algorithm_directions.{$algorithm}", 'desc');

        // The rank is determined by counting how many ratings are better than this one, according to the algorithm's direction.
        if ($conservative) {
            if ($direction === 'asc') {
                $score = $rating->rating + 2.0 * $rating->deviation;
                $query->whereRaw('(rating + (2 * deviation)) < ?', [$score]);
            } else {
                $score = $rating->rating - 2.0 * $rating->deviation;
                $query->whereRaw('(rating - (2 * deviation)) > ?', [$score]);
            }
        } else {
            // Use the rating value directly for ranking.
            $query->where('rating', $direction === 'asc' ? '<' : '>', $rating->rating);
        }

        // The rank is the number of ratings that are better than this one, plus one for this rating itself.
        return $query->count() + 1;
    }

    /**
     * Get the rating record for a given model, creating it if necessary.
     *
     * @param  class-string<Model>  $model
     * @param  string|null  $pool
     * @param  string|null  $algorithm
     * @param  int|null  $seasonId
     * @param  bool  $create
     * @return Rating|null
     */
    public function ratingForModel(
        Model $model,
        ?string $pool = null,
        ?string $algorithm = null,
        ?int $seasonId = null,
        bool $create = true,
    ): ?Rating {
        // Use default pool and algorithm if not provided.
        $pool ??= (string) config('rating-kit.default_pool', 'default');
        $algorithm ??= (string) config('rating-kit.default_algorithm', 'glicko2');

        // Query the rating model for a record matching the given model, pool, algorithm, and season.
        $query = $this->ratingModel()::query()
            ->where('rateable_type', $model->getMorphClass())
            ->where('rateable_id', $model->getKey())
            ->where('pool', $pool)
            ->where('algorithm', $algorithm)
            ->where('season_key', $seasonId ?? 0);
        $rating = $query->first();

        // If a rating record was found, or if creation is not allowed, return the found rating (or null).
        if ($rating !== null || ! $create) {
            return $rating;
        }

        // If no rating record was found and creation is allowed, create a new rating record for the model.
        return $this->findOrCreateRating(
            $this->ratingModel(),
            new Participant($model),
            $pool,
            $algorithm,
            $seasonId,
        );
    }

    /**
     * Normalize the teams for a match, ensuring that there are at least two teams and that no participant appears
     * in more than one team.
     *
     * @param  list<Team>  $teams
     * @return list<Team>
     */
    protected function normalizeTeams(array $teams): array
    {
        // Ensure that there are at least two teams in the match.
        if (count($teams) < 2) {
            throw new InvalidMatch('A rated match must contain at least two teams.');
        }

        // Ensure that no participant appears in more than one team in the same match.
        $seen = [];

        foreach ($teams as $team) {
            // Validate that no participant appears in more than one team in the same match.
            foreach ($team->normalizedParticipants() as $participant) {
                $key = $participant->key();

                if (isset($seen[$key])) {
                    throw new InvalidMatch("Participant [{$key}] appears more than once in the same match.");
                }

                $seen[$key] = true;
            }
        }

        // Return the normalized teams as a zero-based array.
        return array_values($teams);
    }

    /**
     * Check if any team in the list contains multiple participants.
     *
     * @param list<Team> $teams
     * @return bool
     */
    protected function containsMultiPlayerTeam(array $teams): bool
    {
        // Iterate through each team and check if it has more than one participant.
        foreach ($teams as $team) {
            if (count($team->participants) > 1) {
                return true;
            }
        }

        // If no team contains multiple participants, return false.
        return false;
    }

    /**
     * Find or create a rating for a participant.
     *
     * @param class-string<Rating> $ratingClass
     * @param Participant $participant
     * @param string $pool
     * @param string $algorithm
     * @param int|null $seasonId
     * @return Rating
     */
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

        // If the rating was newly created, trigger the RatingCreated event to notify listeners.
        return $locked;
    }

    /**
     * Determine the outcome for a given team based on its rank and the ranks of other teams.
     *
     * @param  list<Team>  $teams
     * @param  int  $minimumRank
     * @param  int  $firstPlaceCount
     * @return string Returns 'win', 'draw', or 'loss' based on the team's performance.
     */
    protected function outcomeFor(Team $team, array $teams, int $minimumRank, int $firstPlaceCount): string
    {
        // If all teams have the same rank, the outcome is a draw.
        if (count(array_unique(array_map(static fn (Team $entry): int => $entry->rank, $teams))) === 1) {
            return 'draw';
        }

        // If the team's rank is equal to the minimum rank, determine if it's a win or a draw based on the number of first-place teams.
        if ($team->rank === $minimumRank) {
            return $firstPlaceCount > 1 ? 'draw' : 'win';
        }

        // If the team's rank is greater than the minimum rank, it's a loss.
        return 'loss';
    }

    /**
     * Update the statistics for a rating based on the outcome of a match.
     *
     * @param Rating $rating
     * @param string $outcome
     * @return array{wins: int, draws: int, losses: int, streak: int}
     */
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

    /**
     * Revert the statistics for a rating based on the outcome of a match.
     *
     * @param Rating $rating
     * @param string $outcome
     * @return array{wins: int, draws: int, losses: int}
     */
    protected function revertedStats(Rating $rating, string $outcome): array
    {
        return [
            'wins' => max(0, $rating->wins - ($outcome === 'win' ? 1 : 0)),
            'draws' => max(0, $rating->draws - ($outcome === 'draw' ? 1 : 0)),
            'losses' => max(0, $rating->losses - ($outcome === 'loss' ? 1 : 0)),
        ];
    }

    /**
     * Dispatch an event after the current database transaction has committed.
     *
     * @param  object  $event
     * @return void
     */
    protected function afterCommit(object $event): void
    {
        // Check if event dispatching is enabled in the configuration; if not, return early.
        if (! (bool) config('rating-kit.dispatch_events', true)) {
            return;
        }

        // If there is an active database transaction, register a callback to dispatch the event after the transaction commits.
        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit(static fn () => event($event));

            return;
        }

        // If there is no active transaction, dispatch the event immediately.
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
