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

    /*
    |--------------------------------------------------------------------------
    | Default Algorithm and Pool
    |--------------------------------------------------------------------------
    |
    | This option controls the default algorithm and pool that will be used
    | by the rating system. You may set this to any of the algorithms and
    | pools defined in the configuration below. You may also set this to
    | null to disable the default algorithm and pool, in which case you will
    | need to specify the algorithm and pool when creating a rating.
    |
    */

    'default_algorithm' => env('RATING_KIT_ALGORITHM', 'glicko2'),
    'default_pool' => env('RATING_KIT_POOL', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Initial Rating Parameters
    |--------------------------------------------------------------------------
    |
    | These parameters define the initial rating, deviation, and volatility
    | for new participants in the rating system. You may adjust these values
    | to suit your needs, but be aware that changing these values may affect
    | the accuracy of the rating system.
    |
    */
    
    'initial' => [
        'rating' => 1500.0,
        'deviation' => 350.0,
        'volatility' => 0.06,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provisional Games
    |--------------------------------------------------------------------------
    |
    | This option controls the number of games a participant must play before
    | their rating is considered "provisional". Provisional ratings are less
    | reliable than established ratings, and may be subject to greater
    | fluctuations. You may adjust this value to suit your needs, but be aware
    | that changing this value may affect the accuracy of the rating system.
    |
    */

    'provisional_games' => 10,
    
    /*
    |--------------------------------------------------------------------------
    | Rating Floor and Ceiling
    |--------------------------------------------------------------------------
    |
    | These options control the minimum and maximum ratings that a participant
    | can achieve. You may adjust these values to suit your needs, but be aware
    | that changing these values may affect the accuracy of the rating system.
    |
    */

    'rating_floor' => null,
    'rating_ceiling' => null,

    /*
    |--------------------------------------------------------------------------
    | History Tracking
    |--------------------------------------------------------------------------
    |
    | This option controls whether or not the rating system will track the
    | history of ratings for each participant. If enabled, the system will
    | store a record of each rating change, along with the date and time of
    | the change. You may adjust this value to suit your needs, but be aware
    | that enabling history tracking may increase the storage requirements of
    | the system.
    |
    */

    'history_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Event Dispatching
    |--------------------------------------------------------------------------
    |
    | This option controls whether or not the rating system will dispatch
    | events when ratings are updated. If enabled, the system will dispatch
    | events that you can listen for in your application. You may adjust this
    | value to suit your needs, but be aware that enabling event dispatching
    | may increase the complexity of your application.
    |
    */

    'dispatch_events' => true,

    /*
    |--------------------------------------------------------------------------
    | Morph Key Type
    |--------------------------------------------------------------------------
    |
    | This option controls the type of the morph key used in the rating system.
    | You may set this to either 'string' or 'numeric', depending on your
    | needs. If you are using a string-based morph key, you may need to adjust
    | the database schema to accommodate longer strings. If you are using a
    | numeric-based morph key, you may need to adjust the database schema to
    | accommodate larger integers.
    |
    */

    'morph_key_type' => 'numeric',

    /*
    |--------------------------------------------------------------------------
    | Team Aggregation and Distribution
    |--------------------------------------------------------------------------
    |
    | These options control how team ratings are calculated and distributed.
    | You may set the aggregation method to either 'average' or 'sum', and the
    | distribution method to either 'participation' or 'contribution'. The
    | aggregation method determines how the ratings of individual team members
    | are combined to calculate the team's overall rating, while the distribution
    | method determines how the team's rating is distributed among its members.
    |
    */

    'team_aggregation' => 'average',
    'team_distribution' => 'participation',

    /*
    |--------------------------------------------------------------------------
    | Algorithms
    |--------------------------------------------------------------------------
    |
    | This option defines the available algorithms that can be used by the
    | rating system. You may add or remove algorithms as needed, but be aware
    | that changing these values may affect the accuracy of the rating system.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Algorithm Directions
    |--------------------------------------------------------------------------
    |
    | This option defines the direction of the rating scale for each algorithm.
    | You may set the direction to either 'asc' or 'desc', depending on your
    | needs. If you are using an ascending scale, higher ratings indicate
    | better performance, while lower ratings indicate worse performance. If
    | you are using a descending scale, lower ratings indicate better
    | performance, while higher ratings indicate worse performance.
    |
    */

    'algorithm_directions' => [
        'ingo' => 'asc',
    ],

    /*
    |--------------------------------------------------------------------------
    | Algorithm Options
    |--------------------------------------------------------------------------
    |
    | This option defines the options for each algorithm. You may add or remove
    | options as needed, but be aware that changing these values may affect the
    | accuracy of the rating system.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Decay
    |--------------------------------------------------------------------------
    |
    | This option defines the decay settings for the rating system. You may
    | adjust these values as needed, but be aware that changing them may
    | affect the accuracy of the rating system.
    |
    */

    'decay' => [
        'enabled' => false,
        'inactive_after_days' => 90,
        'period_days' => 30,
        'points_per_period' => 10.0,
        'minimum_rating' => 1200.0,
        'maximum_rating' => 2200.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | This option defines the database tables used by the rating system. You may
    | change these values to suit your needs, but be aware that changing them
    | may affect the accuracy of the rating system.
    |
    */

    'tables' => [
        'seasons' => 'rating_kit_seasons',
        'ratings' => 'rating_kit_ratings',
        'matches' => 'rating_kit_matches',
        'teams' => 'rating_kit_teams',
        'participants' => 'rating_kit_participants',
        'histories' => 'rating_kit_histories',
        'leaderboard_snapshots' => 'rating_kit_leaderboard_snapshots',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | This option defines the models used by the rating system. You may change
    | these values to suit your needs, but be aware that changing them may
    | affect the accuracy of the rating system.
    |
    */

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
