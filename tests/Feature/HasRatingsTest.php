<?php

namespace Tests\Feature;

use EloquentWorks\RatingKit\Facades\RatingKit;
use Tests\Support\User;
use Tests\TestCase;

class HasRatingsTest extends TestCase
{
    public function test_a_rateable_model_can_own_independent_ratings(): void
    {
        $user = User::query()->create(['name' => 'Alpha']);

        $elo = $user->ratingFor('chess.blitz', 'elo');
        $glicko = $user->ratingFor('chess.blitz', 'glicko2');
        $rapid = $user->ratingFor('chess.rapid', 'glicko2');

        self::assertNotNull($elo);
        self::assertNotNull($glicko);
        self::assertNotNull($rapid);
        self::assertTrue($user->hasRating('chess.blitz', 'elo'));
        self::assertCount(3, $user->ratings);
        self::assertSame(['chess.blitz', 'chess.rapid'], $user->ratingPools()->all());
        self::assertSame(['elo', 'glicko2'], $user->ratingAlgorithms('chess.blitz')->all());
    }

    public function test_the_trait_exposes_rating_statistics_rank_and_history(): void
    {
        $winner = User::query()->create(['name' => 'Winner']);
        $loser = User::query()->create(['name' => 'Loser']);

        RatingKit::oneVsOne(
            left: $winner,
            right: $loser,
            result: 'left',
            algorithm: 'elo',
            pool: 'chess.blitz',
        );

        $stats = $winner->ratingStats('chess.blitz', 'elo');

        self::assertSame(1, $stats['games']);
        self::assertSame(1, $stats['wins']);
        self::assertSame(0, $stats['losses']);
        self::assertSame(100.0, $stats['win_rate']);
        self::assertFalse($stats['provisional']);
        self::assertSame(1, $winner->ratingRank('chess.blitz', 'elo'));
        self::assertCount(1, $winner->ratingHistory('chess.blitz', 'elo'));
        self::assertNotNull($winner->latestRatingChange('chess.blitz', 'elo'));
        self::assertNotNull($winner->latestRatingParticipation('chess.blitz', 'elo'));
        self::assertCount(1, $winner->recentRatedMatches());
    }

    public function test_the_trait_can_adjust_set_and_reset_a_rating(): void
    {
        $user = User::query()->create(['name' => 'Administered']);

        $user->setRating(1800, 'legacy_import', 'chess.rapid', 'elo');
        self::assertSame(1800.0, $user->currentRating('chess.rapid', 'elo'));

        $user->adjustRating(25, 'tournament_bonus', 'chess.rapid', 'elo');
        self::assertSame(1825.0, $user->currentRating('chess.rapid', 'elo'));

        $user->resetRating('chess.rapid', 'elo');
        self::assertSame(1500.0, $user->currentRating('chess.rapid', 'elo'));
        self::assertCount(3, $user->ratingHistory('chess.rapid', 'elo'));
    }
}
