<?php

namespace Tests\Unit;

use EloquentWorks\RatingKit\Algorithms\BradleyTerryAlgorithm;
use EloquentWorks\RatingKit\Algorithms\DwzAlgorithm;
use EloquentWorks\RatingKit\Algorithms\EgfAlgorithm;
use EloquentWorks\RatingKit\Algorithms\EloAlgorithm;
use EloquentWorks\RatingKit\Algorithms\FideEloAlgorithm;
use EloquentWorks\RatingKit\Algorithms\FifaAlgorithm;
use EloquentWorks\RatingKit\Algorithms\Glicko2Algorithm;
use EloquentWorks\RatingKit\Algorithms\GlickoAlgorithm;
use EloquentWorks\RatingKit\Algorithms\IngoAlgorithm;
use EloquentWorks\RatingKit\Algorithms\MarginOfVictoryEloAlgorithm;
use EloquentWorks\RatingKit\Algorithms\PlackettLuceAlgorithm;
use EloquentWorks\RatingKit\Algorithms\ThurstoneMostellerAlgorithm;
use EloquentWorks\RatingKit\Algorithms\UscfAlgorithm;
use EloquentWorks\RatingKit\Algorithms\WengLinAlgorithm;
use EloquentWorks\RatingKit\Contracts\RatingAlgorithm;
use EloquentWorks\RatingKit\Data\CompetitorInput;
use EloquentWorks\RatingKit\Data\MatchInput;
use EloquentWorks\RatingKit\Data\RatingState;
use EloquentWorks\RatingKit\Data\TeamInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AlgorithmsTest extends TestCase
{
    /** @return iterable<string, array{class-string<RatingAlgorithm>}> */
    public static function algorithms(): iterable
    {
        yield 'Elo' => [EloAlgorithm::class];
        yield 'margin-of-victory Elo' => [MarginOfVictoryEloAlgorithm::class];
        yield 'FIDE Elo' => [FideEloAlgorithm::class];
        yield 'USCF' => [UscfAlgorithm::class];
        yield 'Glicko' => [GlickoAlgorithm::class];
        yield 'Glicko-2' => [Glicko2Algorithm::class];
        yield 'Weng-Lin' => [WengLinAlgorithm::class];
        yield 'Bradley-Terry' => [BradleyTerryAlgorithm::class];
        yield 'Plackett-Luce' => [PlackettLuceAlgorithm::class];
        yield 'Thurstone-Mosteller' => [ThurstoneMostellerAlgorithm::class];
        yield 'DWZ' => [DwzAlgorithm::class];
        yield 'EGF' => [EgfAlgorithm::class];
        yield 'Ingo' => [IngoAlgorithm::class];
        yield 'FIFA' => [FifaAlgorithm::class];
    }

    #[DataProvider('algorithms')]
    public function test_algorithm_rates_three_teams_of_three_players(string $algorithmClass): void
    {
        $algorithm = new $algorithmClass([]);
        $match = new MatchInput([
            new TeamInput([$this->competitor('a'), $this->competitor('b'), $this->competitor('c')], 1, 12),
            new TeamInput([$this->competitor('d'), $this->competitor('e'), $this->competitor('f')], 2, 8),
            new TeamInput([$this->competitor('g'), $this->competitor('h'), $this->competitor('i')], 3, 4),
        ]);

        $changes = $algorithm->rate($match);

        self::assertCount(9, $changes);
        self::assertNotSame(1500.0, $changes->get('a')->after->rating);
        self::assertNotSame(1500.0, $changes->get('g')->after->rating);
        self::assertTrue(is_finite($changes->get('a')->after->rating));
        self::assertTrue(is_finite($changes->get('g')->after->rating));
    }

    public function test_draw_keeps_equally_rated_elo_players_equal(): void
    {
        $algorithm = new EloAlgorithm(['k_factor' => 32]);
        $changes = $algorithm->rate(new MatchInput([
            new TeamInput([$this->competitor('left')], 1),
            new TeamInput([$this->competitor('right')], 1),
        ]));

        self::assertSame(1500.0, $changes->get('left')->after->rating);
        self::assertSame(1500.0, $changes->get('right')->after->rating);
    }

    private function competitor(string $key): CompetitorInput
    {
        return new CompetitorInput($key, new RatingState);
    }
}
