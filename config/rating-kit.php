<?php

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
use EloquentWorks\RatingKit\Models\LeaderboardSnapshot;
use EloquentWorks\RatingKit\Models\Rating;
use EloquentWorks\RatingKit\Models\RatingHistory;
use EloquentWorks\RatingKit\Models\RatingMatch;
use EloquentWorks\RatingKit\Models\RatingParticipant;
use EloquentWorks\RatingKit\Models\RatingSeason;
use EloquentWorks\RatingKit\Models\RatingTeam;

return [
    'default_algorithm' => env('RATING_KIT_ALGORITHM', 'glicko2'),
    'default_pool' => env('RATING_KIT_POOL', 'default'),

    'initial' => [
        'rating' => 1500.0,
        'deviation' => 350.0,
        'volatility' => 0.06,
    ],

    'provisional_games' => 10,
    'rating_floor' => null,
    'rating_ceiling' => null,
    'history_enabled' => true,
    'dispatch_events' => true,
    'morph_key_type' => 'numeric',

    'team_aggregation' => 'average',
    'team_distribution' => 'participation',

    'algorithms' => [
        'elo' => EloAlgorithm::class,
        'elo_mov' => MarginOfVictoryEloAlgorithm::class,
        'fide' => FideEloAlgorithm::class,
        'uscf' => UscfAlgorithm::class,
        'glicko' => GlickoAlgorithm::class,
        'glicko2' => Glicko2Algorithm::class,
        'weng_lin' => WengLinAlgorithm::class,
        'bradley_terry' => BradleyTerryAlgorithm::class,
        'plackett_luce' => PlackettLuceAlgorithm::class,
        'thurstone_mosteller' => ThurstoneMostellerAlgorithm::class,
        'dwz' => DwzAlgorithm::class,
        'egf' => EgfAlgorithm::class,
        'ingo' => IngoAlgorithm::class,
        'fifa' => FifaAlgorithm::class,
    ],

    'algorithm_directions' => [
        'ingo' => 'asc',
    ],

    'algorithm_options' => [
        'elo' => ['k_factor' => 32.0, 'scale' => 400.0],
        'elo_mov' => ['k_factor' => 32.0, 'scale' => 400.0, 'mov_exponent' => 0.8],
        'fide' => ['scale' => 400.0],
        'uscf' => ['scale' => 400.0],
        'glicko' => [],
        'glicko2' => ['tau' => 0.5, 'epsilon' => 0.000001],
        'weng_lin' => ['beta' => 200.0],
        'bradley_terry' => ['k_factor' => 24.0, 'scale' => 400.0],
        'plackett_luce' => ['k_factor' => 24.0, 'scale' => 400.0],
        'thurstone_mosteller' => ['k_factor' => 24.0, 'beta' => 200.0],
        'dwz' => ['development_coefficient' => 30.0],
        'egf' => ['con' => 116.0],
        'ingo' => ['development_coefficient' => 10.0],
        'fifa' => ['importance' => 25.0],
    ],

    'decay' => [
        'enabled' => false,
        'inactive_after_days' => 90,
        'period_days' => 30,
        'points_per_period' => 10.0,
        'minimum_rating' => 1200.0,
        'maximum_rating' => 2200.0,
    ],

    'tables' => [
        'seasons' => 'rating_kit_seasons',
        'ratings' => 'rating_kit_ratings',
        'matches' => 'rating_kit_matches',
        'teams' => 'rating_kit_teams',
        'participants' => 'rating_kit_participants',
        'histories' => 'rating_kit_histories',
        'leaderboard_snapshots' => 'rating_kit_leaderboard_snapshots',
    ],

    'models' => [
        'season' => RatingSeason::class,
        'rating' => Rating::class,
        'match' => RatingMatch::class,
        'team' => RatingTeam::class,
        'participant' => RatingParticipant::class,
        'history' => RatingHistory::class,
        'leaderboard_snapshot' => LeaderboardSnapshot::class,
    ],
];
