# 📊 Leaderboards

## 🥇 Basic Leaderboard

```php
$entries = RatingKit::leaderboard(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    limit: 100,
);
```

Competitors with equal scores share the same competition rank. For example, two tied leaders are ranked `1, 1, 3`.

Each entry contains:

```php
[
    'rank' => 1,
    'rating' => $ratingModel,
    'score' => 1842.31,
]
```

## 🐣 Provisional Competitors

Provisional ratings are excluded by default:

```php
includeProvisional: false
```

Include them when needed:

```php
RatingKit::leaderboard(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    includeProvisional: true,
);
```

## 🛡️ Conservative Ranking

For uncertainty-aware systems, rank by:

```text
rating - (2 × deviation)
```

```php
RatingKit::leaderboard(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    conservative: true,
);
```

## 🔢 Individual Rank

```php
$rank = RatingKit::rankOf(
    model: $user,
    pool: 'chess.blitz',
    algorithm: 'glicko2',
);
```

The result is `null` when the user has no matching rating or is excluded as provisional.

## 📸 Persistent Snapshots

```php
$snapshot = RatingKit::snapshotLeaderboard(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    limit: 100,
);
```

Or:

```bash
php artisan rating-kit:snapshot --pool=chess.blitz --algorithm=glicko2 --limit=100
```


## 📈 Pool Statistics

```php
$statistics = RatingKit::poolStatistics(
    pool: 'chess.blitz',
    algorithm: 'glicko2',
    seasonId: null,
);
```

The result contains:

```php
[
    'count' => 120,
    'established' => 104,
    'provisional' => 16,
    'average' => 1518.42,
    'minimum' => 982.14,
    'maximum' => 2241.77,
    'median' => 1507.63,
]
```
