# Laravel RatingKit 🏆

[![Tests](https://github.com/EloquentWorks/RatingKit/actions/workflows/tests.yml/badge.svg)](https://github.com/EloquentWorks/RatingKit/actions/workflows/tests.yml)
[![Latest Release](https://img.shields.io/github/v/release/EloquentWorks/RatingKit)](https://github.com/EloquentWorks/RatingKit/releases)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Laravel RatingKit is a comprehensive, extensible rating engine for Laravel applications.

It supports **1v1, 2v2, 3v3, arbitrary N-vs-N team matches, free-for-alls, and competitions containing multiple teams**. Ratings can be isolated by game, variant, queue, time control, season, region, or any custom pool.

## ✨ Features

- Fourteen bundled rating algorithms
- Any number of players per team
- Any number of teams per match
- Free-for-all and ranked-placement results
- Draws and tied ranks
- Weighted participation and substitutes
- Polymorphic `HasRatings` trait for users, teams, bots, clans, and other models
- Model helpers for current ratings, statistics, ranks, histories, matches, and adjustments
- Separate rating pools
- Seasonal ratings
- Provisional ratings
- Rating deviation and volatility
- Rating floors and ceilings
- Win, draw, loss, and streak statistics
- Rating histories and audit trails
- Safe rollback of the latest match
- Idempotent external match IDs
- Direction-aware recurring inactivity decay
- Leaderboards, shared ranks, and conservative leaderboards
- Persistent leaderboard snapshots
- Match outcome prediction and match-quality scoring
- Pool statistics including average, median, minimum, and maximum
- Manual rating adjustments and resets
- Custom Eloquent models and table names
- Numeric, UUID, and ULID rateable model keys
- Per-match algorithm option snapshots
- Pluggable custom algorithms
- Transactional writes and row locking
- Lifecycle events
- Artisan installation, decay, snapshot, and season commands
- PHPUnit, PHPStan, Pint, and GitHub Actions support

## 🧮 Bundled Algorithms

| Key | Algorithm | Typical use |
|---|---|---|
| `elo` | Elo | General head-to-head and team competition |
| `elo_mov` | Margin-of-victory Elo | Score-based sports and games |
| `fide` | FIDE-style Elo | Chess-style rating pools |
| `uscf` | USCF-style Elo | Development-sensitive chess ratings |
| `glicko` | Glicko | Ratings with uncertainty |
| `glicko2` | Glicko-2 | Uncertainty and volatility tracking |
| `weng_lin` | Weng-Lin / OpenSkill-style | Bayesian team and multiplayer ratings |
| `bradley_terry` | Bradley-Terry | Pairwise comparison models |
| `plackett_luce` | Plackett-Luce | Ordered multi-team and free-for-all results |
| `thurstone_mosteller` | Thurstone-Mosteller | Gaussian performance modeling |
| `dwz` | DWZ-style | Development-sensitive chess ratings |
| `egf` | EGF-style | Go-style rating adaptation |
| `ingo` | Ingo-style | Lower-is-stronger historical rating systems |
| `fifa` | FIFA-style Elo | Importance-weighted team competition |

Federation-named drivers are configurable practical adaptations. They are not claims of certification by the named federation.

## 🚀 Installation

Install the package through Composer:

```bash
composer require eloquent-works/rating-kit
```

Publish the configuration and migrations, then migrate:

```bash
php artisan rating-kit:install --migrate
```

Add `HasRatings` to every model that can receive a rating:

```php
use EloquentWorks\RatingKit\Traits\HasRatings;

class User extends Authenticatable
{
    use HasRatings;
}
```

The trait gives the model polymorphic rating relationships and convenient helpers:

```php
$user->ratings;
$user->ratingFor('chess.blitz', 'glicko2');
$user->currentRating('chess.blitz', 'glicko2');
$user->ratingStats('chess.blitz', 'glicko2');
$user->ratingRank('chess.blitz', 'glicko2');
$user->ratingHistory('chess.blitz', 'glicko2');
$user->recentRatedMatches();
```

A user can own independent ratings for every pool, algorithm, and season. See the full [HasRatings guide](docs/has-ratings.md).

## ⚡ One Player vs One Player

```php
use EloquentWorks\RatingKit\Facades\RatingKit;

$match = RatingKit::oneVsOne(
    left: $winner,
    right: $loser,
    result: 'left',
    algorithm: 'glicko2',
    pool: 'chess.blitz',
    externalId: 'game-10001',
);
```

Record a draw:

```php
RatingKit::oneVsOne(
    left: $playerA,
    right: $playerB,
    result: 'draw',
    algorithm: 'elo',
    pool: 'chess.rapid',
);
```

## 👥 Two Players vs Two Players

```php
RatingKit::teamVsTeam(
    left: [$playerA, $playerB],
    right: [$playerC, $playerD],
    result: 'left',
    algorithm: 'glicko2',
    pool: 'chess.teams.blitz',
);
```

## 🛡️ Three Players vs Three Players

```php
RatingKit::teamVsTeam(
    left: [$playerA, $playerB, $playerC],
    right: [$playerD, $playerE, $playerF],
    result: 'right',
    algorithm: 'weng_lin',
    pool: 'arena.3v3.ranked',
);
```

The same API supports 4v4, 5v5, 10v10, or any other team size.

## 🌐 Multiple Teams in One Match

```php
use EloquentWorks\RatingKit\Data\RecordMatch;
use EloquentWorks\RatingKit\Data\Team;

$match = RatingKit::record(new RecordMatch(
    teams: [
        new Team([$a, $b], rank: 1, score: 18, name: 'Red'),
        new Team([$c, $d], rank: 2, score: 14, name: 'Blue'),
        new Team([$e, $f], rank: 3, score: 9, name: 'Green'),
    ],
    algorithm: 'plackett_luce',
    pool: 'arena.trios',
    externalId: 'match-9001',
));
```

Teams may share a rank to represent a tie.

## 🥇 Free-for-All Results

```php
RatingKit::freeForAll([
    ['participant' => $winner, 'rank' => 1, 'score' => 25],
    ['participant' => $second, 'rank' => 2, 'score' => 18],
    ['participant' => $third, 'rank' => 3, 'score' => 12],
    ['participant' => $fourth, 'rank' => 3, 'score' => 12],
], algorithm: 'plackett_luce', pool: 'battle-royale.solo');
```

## ⚖️ Weighted Participants

Use weights for substitutes, partial participation, handicaps, or unequal contribution:

```php
use EloquentWorks\RatingKit\Data\Participant;

RatingKit::teamVsTeam(
    left: [
        new Participant($starter, weight: 1.0),
        new Participant($substitute, weight: 0.35),
    ],
    right: [$opponentA, $opponentB],
    result: 'left',
    algorithm: 'elo',
    pool: 'football.doubles',
);
```

## 🗂️ Separate Rating Pools

The same user can hold independent ratings:

```php
$user->currentRating('chess.standard', 'glicko2');
$user->currentRating('chess.blitz', 'glicko2');
$user->currentRating('chess.teams', 'weng_lin');
$user->currentRating('variant.atomic', 'elo');
```

Pools work well for:

- Different games
- Variants
- Time controls
- Ranked and casual queues
- Team sizes
- Regions
- Platforms
- Tournament circuits

## 📅 Seasons

```php
$season = RatingKit::createSeason(
    name: 'Season 1',
    slug: 'season-1',
    pool: 'arena.ranked',
    startsAt: now(),
    endsAt: now()->addMonths(3),
);

RatingKit::teamVsTeam(
    left: $redTeam,
    right: $blueTeam,
    result: 'left',
    algorithm: 'glicko2',
    pool: 'arena.ranked',
    seasonId: $season->id,
);

RatingKit::closeSeason($season);
```

Closing a season automatically captures a final leaderboard for every algorithm used during that season.

## 📊 Leaderboards

```php
$leaderboard = RatingKit::leaderboard(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    limit: 100,
    includeProvisional: false,
);
```

Use a conservative score based on rating uncertainty:

```php
$leaderboard = RatingKit::leaderboard(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    conservative: true,
);
```

Competitors with the same score share the same competition rank. Retrieve one competitor's rank:

```php
$rank = RatingKit::rankOf($user, 'chess.blitz', 'glicko2');
```

## 🔮 Match Predictions

```php
$prediction = RatingKit::predict([
    new Team([$playerA, $playerB], rank: 1),
    new Team([$playerC, $playerD], rank: 2),
], pool: 'arena.2v2', algorithm: 'glicko2');
```

The result contains a normalized estimated winning share for each team.

Measure how balanced the proposed match is on a scale from `0.0` to `1.0`:

```php
$quality = RatingKit::matchQuality([
    new Team([$playerA, $playerB], rank: 1),
    new Team([$playerC, $playerD], rank: 2),
], pool: 'arena.2v2', algorithm: 'glicko2');
```

## 📈 Pool Statistics

```php
$statistics = RatingKit::poolStatistics(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
);
```

The result includes total, established, and provisional counts together with average, median, minimum, and maximum ratings.

## 🧾 Rating History

```php
$history = $user->ratingHistory('chess.blitz', 'glicko2');
$peak = $user->peakRating('chess.blitz', 'glicko2');
```

Every history record can preserve:

- Rating before and after
- Rating deviation before and after
- Volatility before and after
- Match ID
- Adjustment reason
- Custom metadata

## ↩️ Rollback and Voiding

The latest match for every participant can be safely voided:

```php
RatingKit::void($match, 'The result was entered incorrectly.');
```

RatingKit refuses an unsafe rollback when one of the participants already has a later result.

## 🛠️ Manual Adjustments

```php
RatingKit::adjust(
    model: $user,
    amount: 25,
    reason: 'tournament_bonus',
    pool: 'chess.standard',
    algorithm: 'glicko2',
);

RatingKit::setRating($user, 1800, reason: 'verified_import');
RatingKit::resetRating($user);
```

Manual changes are written to rating history.

## 💤 Inactivity Decay

Decay is disabled by default. When enabled, eligible ratings are adjusted once per configured period after the inactivity threshold. Higher-is-better algorithms move down toward the configured minimum; lower-is-better algorithms move up toward the configured maximum.

```php
'decay' => [
    'enabled' => true,
    'inactive_after_days' => 90,
    'period_days' => 30,
    'points_per_period' => 10.0,
    'minimum_rating' => 1200.0,
    'maximum_rating' => 2200.0,
],
```

## 🧩 Custom Algorithms

Implement `RatingAlgorithm` and register it at runtime:

```php
RatingKit::algorithms()->extend('my_algorithm', MyAlgorithm::class);
```

Or add it to `config/rating-kit.php`:

```php
'algorithms' => [
    'my_algorithm' => App\Ratings\MyAlgorithm::class,
],
```

## 📣 Events

RatingKit dispatches events for:

- `MatchRated`
- `MatchVoided`
- `RatingUpdated`
- `RatingAdjusted`
- `SeasonClosed`
- `LeaderboardSnapshotted`

Event dispatching can be disabled in configuration.

## 🧰 Artisan Commands

```bash
php artisan rating-kit:install --migrate
php artisan rating-kit:decay
php artisan rating-kit:snapshot --pool=chess.blitz --algorithm=glicko2
php artisan rating-kit:close-season season-1
```

## 📚 Documentation

The complete documentation is available in [`docs/README.md`](docs/README.md).

- [Installation](docs/installation.md)
- [Quick start](docs/quick-start.md)
- [Algorithms](docs/algorithms.md)
- [Team and multiplayer matches](docs/team-matches.md)
- [Pools and variants](docs/rating-pools.md)
- [Seasons](docs/seasons.md)
- [Leaderboards](docs/leaderboards.md)
- [History and rollback](docs/history-and-rollback.md)
- [Configuration](docs/configuration.md)
- [Custom algorithms](docs/custom-algorithms.md)
- [API reference](docs/api-reference.md)

## ✅ Supported Versions

- PHP 8.2+
- Laravel 12
- Laravel 13

## 🧪 Quality Checks

```bash
composer validate --strict
composer audit
composer format:test
composer analyse
composer test
```

Or run the complete quality suite:

```bash
composer quality
```

## 🔐 Security

Please review [SECURITY.md](SECURITY.md) for responsible vulnerability reporting.

## 🤝 Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

## 📄 License

Laravel RatingKit is open-source software licensed under the [MIT license](LICENSE).
