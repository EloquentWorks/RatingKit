<?php

namespace Tests\Feature;

use EloquentWorks\RatingKit\Data\RecordMatch;
use EloquentWorks\RatingKit\Data\Team;
use EloquentWorks\RatingKit\Enums\MatchStatus;
use EloquentWorks\RatingKit\Models\RatingMatch;
use EloquentWorks\RatingKit\RatingKitManager;
use Tests\Support\User;
use Tests\TestCase;

class RatingKitManagerTest extends TestCase
{
    public function test_it_rates_two_players_against_two_players(): void
    {
        $players = $this->users(4);
        $match = app(RatingKitManager::class)->teamVsTeam(
            [$players[0], $players[1]],
            [$players[2], $players[3]],
            result: 'left',
            algorithm: 'elo',
            pool: 'chess.teams.blitz',
        );

        self::assertSame(MatchStatus::Processed, $match->status);
        self::assertCount(4, $match->participants);
        self::assertSame(32.0, $match->algorithm_options['k_factor']);
        self::assertGreaterThan(1500.0, $players[0]->currentRating('chess.teams.blitz', 'elo'));
        self::assertLessThan(1500.0, $players[2]->currentRating('chess.teams.blitz', 'elo'));
    }

    public function test_it_rates_three_players_against_three_players(): void
    {
        $players = $this->users(6);
        $match = app(RatingKitManager::class)->teamVsTeam(
            array_slice($players, 0, 3),
            array_slice($players, 3, 3),
            result: 'right',
            algorithm: 'glicko2',
            pool: 'arena.3v3',
        );

        self::assertCount(6, $match->participants);
        self::assertLessThan(1500.0, $players[0]->currentRating('arena.3v3', 'glicko2'));
        self::assertGreaterThan(1500.0, $players[3]->currentRating('arena.3v3', 'glicko2'));
    }

    public function test_it_rates_multiple_teams_and_free_for_all_results(): void
    {
        $players = $this->users(4);
        $match = app(RatingKitManager::class)->freeForAll([
            ['participant' => $players[2], 'rank' => 1],
            ['participant' => $players[0], 'rank' => 2],
            ['participant' => $players[3], 'rank' => 2],
            ['participant' => $players[1], 'rank' => 4],
        ], algorithm: 'plackett_luce', pool: 'battle-royale');

        self::assertCount(4, $match->teams);
        self::assertGreaterThan(
            $players[1]->currentRating('battle-royale', 'plackett_luce'),
            $players[2]->currentRating('battle-royale', 'plackett_luce'),
        );
    }

    public function test_external_ids_make_recording_idempotent(): void
    {
        $players = $this->users(2);
        $ratingKit = app(RatingKitManager::class);
        $request = new RecordMatch(
            [new Team([$players[0]], 1), new Team([$players[1]], 2)],
            algorithm: 'elo',
            externalId: 'game-123',
        );

        $first = $ratingKit->record($request);
        $second = $ratingKit->record($request);

        self::assertSame($first->getKey(), $second->getKey());
        self::assertSame(1, RatingMatch::query()->count());
        self::assertSame(1, $players[0]->ratingFor(algorithm: 'elo')->games);
    }

    public function test_latest_match_can_be_voided_and_ratings_restored(): void
    {
        $players = $this->users(2);
        $ratingKit = app(RatingKitManager::class);
        $match = $ratingKit->oneVsOne($players[0], $players[1], 'left', 'elo');

        $ratingKit->void($match, 'Result entered incorrectly');

        self::assertSame(MatchStatus::Voided, $match->fresh()->status);
        self::assertSame(1500.0, $players[0]->currentRating(algorithm: 'elo'));
        self::assertSame(1500.0, $players[1]->currentRating(algorithm: 'elo'));
    }

    public function test_leaderboard_can_use_conservative_ratings(): void
    {
        $players = $this->users(3);
        $ratingKit = app(RatingKitManager::class);
        $ratingKit->oneVsOne($players[0], $players[1], 'left', 'elo', 'ranked');
        $ratingKit->oneVsOne($players[0], $players[2], 'left', 'elo', 'ranked');

        $leaderboard = $ratingKit->leaderboard('ranked', 'elo', includeProvisional: true, conservative: true);

        self::assertSame($players[0]->getKey(), $leaderboard->first()['rating']->rateable_id);
    }

    public function test_match_quality_is_high_for_balanced_teams(): void
    {
        $players = $this->users(4);
        $ratingKit = app(RatingKitManager::class);

        $quality = $ratingKit->matchQuality([
            new Team([$players[0], $players[1]], 1),
            new Team([$players[2], $players[3]], 2),
        ], algorithm: 'elo');

        self::assertEqualsWithDelta(1.0, $quality, 0.000001);
    }

    public function test_pool_statistics_summarize_the_selected_pool(): void
    {
        $players = $this->users(3);
        $ratingKit = app(RatingKitManager::class);
        $ratingKit->setRating($players[0], 1400, pool: 'stats', algorithm: 'elo');
        $ratingKit->setRating($players[1], 1500, pool: 'stats', algorithm: 'elo');
        $ratingKit->setRating($players[2], 1700, pool: 'stats', algorithm: 'elo');

        $statistics = $ratingKit->poolStatistics('stats', 'elo');

        self::assertSame(3, $statistics['count']);
        self::assertSame(1400.0, $statistics['minimum']);
        self::assertSame(1700.0, $statistics['maximum']);
        self::assertSame(1500.0, $statistics['median']);
        self::assertEqualsWithDelta(1533.333333, $statistics['average'], 0.000001);
    }

    public function test_equal_scores_share_the_same_leaderboard_rank(): void
    {
        $players = $this->users(3);
        $ratingKit = app(RatingKitManager::class);
        $ratingKit->setRating($players[0], 1700, pool: 'ties', algorithm: 'elo');
        $ratingKit->setRating($players[1], 1700, pool: 'ties', algorithm: 'elo');
        $ratingKit->setRating($players[2], 1600, pool: 'ties', algorithm: 'elo');

        $leaderboard = $ratingKit->leaderboard('ties', 'elo', includeProvisional: true);

        self::assertSame([1, 1, 3], $leaderboard->pluck('rank')->all());
    }

    /** @return list<User> */
    private function users(int $count): array
    {
        $users = [];

        foreach (range(1, $count) as $index) {
            $users[] = User::query()->create(['name' => "Player {$index}"]);
        }

        return $users;
    }
}
