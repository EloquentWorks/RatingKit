# 🧬 HasRatings Trait

The `HasRatings` trait turns any Eloquent model into a RatingKit competitor.

Use it on users, teams, bots, clans, organizations, or any model that can participate in a rated result.

## 🚀 Model Setup

```php
use EloquentWorks\RatingKit\Traits\HasRatings;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasRatings;
}
```

The trait adds two polymorphic relationships:

```php
$user->ratings();
$user->ratingParticipations();
```

One model can own many independent ratings. A rating is uniquely identified by:

```text
rateable model + pool + algorithm + season
```

That means the same user can simultaneously have different ratings for blitz, rapid, teams, regions, variants, and seasons.

## 🏆 Retrieve or Create a Rating

```php
$rating = $user->ratingFor(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    seasonId: $season?->id,
);
```

By default, `ratingFor()` creates a rating using the configured initial values when one does not exist.

Read without creating:

```php
$rating = $user->ratingFor(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    create: false,
);
```

Check whether the rating exists:

```php
$user->hasRating('chess.blitz', 'glicko2');
```

## 📊 Current Rating Values

```php
$user->currentRating('chess.blitz', 'glicko2');
$user->ratingDeviation('chess.blitz', 'glicko2');
$user->ratingVolatility('chess.blitz', 'glicko2');
$user->conservativeRating('chess.blitz', 'glicko2');
```

The conservative score defaults to:

```text
rating - (2 × deviation)
```

For lower-is-better algorithms, it uses:

```text
rating + (2 × deviation)
```

## 📈 Statistics

Retrieve a complete statistics array:

```php
$stats = $user->ratingStats('arena.3v3', 'weng_lin');
```

Returned values include:

```php
[
    'rating' => 1574.25,
    'deviation' => 91.2,
    'volatility' => 0.058,
    'games' => 24,
    'wins' => 15,
    'draws' => 3,
    'losses' => 6,
    'win_rate' => 62.5,
    'streak' => 4,
    'provisional' => false,
]
```

Individual helpers are also available:

```php
$user->ratedGames('arena.3v3', 'weng_lin');
$user->ratingWins('arena.3v3', 'weng_lin');
$user->ratingDraws('arena.3v3', 'weng_lin');
$user->ratingLosses('arena.3v3', 'weng_lin');
$user->ratingWinRate('arena.3v3', 'weng_lin');
$user->ratingStreak('arena.3v3', 'weng_lin');
$user->isProvisional('arena.3v3', 'weng_lin');
$user->hasEstablishedRating('arena.3v3', 'weng_lin');
```

Positive streak values represent consecutive wins. Negative values represent consecutive losses. A draw resets the streak to zero.

## 🥇 Rank

Retrieve the model's current leaderboard rank:

```php
$rank = $user->ratingRank(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
);
```

Include provisional competitors:

```php
$rank = $user->ratingRank(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    includeProvisional: true,
);
```

Use conservative ranking:

```php
$rank = $user->ratingRank(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    conservative: true,
);
```

## 🗂️ Pools, Algorithms, and Seasons

Retrieve all ratings in one pool:

```php
$ratings = $user->ratingsForPool('chess.blitz');
```

Retrieve every pool that the model has entered:

```php
$pools = $user->ratingPools();
```

Retrieve ratings using one algorithm:

```php
$ratings = $user->ratingsUsingAlgorithm('glicko2');
```

Retrieve algorithms used in a pool:

```php
$algorithms = $user->ratingAlgorithms('chess.blitz');
```

Retrieve all ratings from one season:

```php
$ratings = $user->seasonRatings($season->id);
```

Use `null` to retrieve lifetime, non-season ratings:

```php
$lifetimeRatings = $user->seasonRatings(null);
```

## 🧾 Rating History

Retrieve the complete history for one rating identity:

```php
$history = $user->ratingHistory('chess.blitz', 'glicko2');
```

Limit the result:

```php
$history = $user->ratingHistory('chess.blitz', 'glicko2', limit: 25);
```

Retrieve the latest change:

```php
$change = $user->latestRatingChange('chess.blitz', 'glicko2');
```

Retrieve the best rating reached by the competitor:

```php
$peak = $user->peakRating('chess.blitz', 'glicko2');
```

`peakRating()` respects the algorithm's configured direction. It returns the highest value for higher-is-better algorithms and the lowest value for lower-is-better algorithms.

## 🎮 Match Participation

Retrieve recent participant records with their match, team, and rating loaded:

```php
$participations = $user->recentRatingParticipations(
    limit: 20,
    pool: 'arena.3v3',
    algorithm: 'weng_lin',
);
```

Retrieve the latest participation:

```php
$participation = $user->latestRatingParticipation('arena.3v3', 'weng_lin');
```

Build a query for every rated match involving the model:

```php
$matches = $user->ratedMatches()
    ->where('pool', 'arena.3v3')
    ->latest('occurred_at')
    ->paginate();
```

Retrieve recent matches directly:

```php
$matches = $user->recentRatedMatches(20);
```

## 🛠️ Manual Adjustments

Add or subtract rating points:

```php
$user->adjustRating(
    amount: 25,
    reason: 'tournament_bonus',
    pool: 'chess.blitz',
    algorithm: 'glicko2',
);
```

Set an exact value:

```php
$user->setRating(
    value: 1800,
    reason: 'legacy_import',
    pool: 'chess.blitz',
    algorithm: 'glicko2',
);
```

Reset to the configured initial rating:

```php
$user->resetRating(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
);
```

These methods use the same transactional manager operations as the facade and create history records when history is enabled.

## 👥 Models Other Than User

The trait is fully polymorphic:

```php
class Team extends Model
{
    use HasRatings;
}

class Bot extends Model
{
    use HasRatings;
}

class Clan extends Model
{
    use HasRatings;
}
```

Different model classes may compete in the same package installation. Avoid placing the same logical competitor into one match more than once under different model identities.
