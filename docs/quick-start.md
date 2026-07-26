# ⚡ Quick Start

## 1️⃣ Record a 1v1 Match

```php
use EloquentWorks\RatingKit\Facades\RatingKit;

$match = RatingKit::oneVsOne(
    left: $winner,
    right: $loser,
    result: 'left',
    algorithm: 'glicko2',
    pool: 'chess.blitz',
);
```

## 2️⃣ Read the New Ratings

```php
$winnerRating = $winner->currentRating('chess.blitz', 'glicko2');
$loserRating = $loser->currentRating('chess.blitz', 'glicko2');
```

## 3️⃣ Record a 2v2 Match

```php
RatingKit::teamVsTeam(
    left: [$a, $b],
    right: [$c, $d],
    result: 'left',
    algorithm: 'weng_lin',
    pool: 'arena.2v2',
);
```

## 4️⃣ Record a 3v3 Match

```php
RatingKit::teamVsTeam(
    left: [$a, $b, $c],
    right: [$d, $e, $f],
    result: 'draw',
    algorithm: 'glicko2',
    pool: 'arena.3v3',
);
```

## 5️⃣ Build a Leaderboard

```php
$leaderboard = RatingKit::leaderboard(
    pool: 'arena.3v3',
    algorithm: 'glicko2',
    limit: 50,
);
```

## 6️⃣ Use an External Match ID

External IDs make repeat submissions idempotent:

```php
RatingKit::oneVsOne(
    left: $winner,
    right: $loser,
    result: 'left',
    externalId: 'provider-match-8182',
);
```

Submitting the same external ID again returns the existing match instead of applying ratings twice.
