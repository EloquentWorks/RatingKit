# 📅 Rating Seasons

Seasons provide independent rating records inside a pool.

## ➕ Create a Season

```php
$season = RatingKit::createSeason(
    name: 'Summer 2026',
    slug: 'summer-2026',
    pool: 'arena.ranked',
    startsAt: now(),
    endsAt: now()->addMonths(3),
    metadata: ['rewards' => 'summer-cup'],
);
```

## 🎯 Rate Inside a Season

```php
RatingKit::oneVsOne(
    left: $winner,
    right: $loser,
    result: 'left',
    algorithm: 'glicko2',
    pool: 'arena.ranked',
    seasonId: $season->id,
);
```

## 📊 Seasonal Leaderboard

```php
$leaderboard = RatingKit::leaderboard(
    pool: 'arena.ranked',
    algorithm: 'glicko2',
    seasonId: $season->id,
);
```

## 🔒 Close a Season

```php
RatingKit::closeSeason($season);
```

Or use Artisan:

```bash
php artisan rating-kit:close-season summer-2026
```

By default, closing creates a final snapshot for every algorithm that has ratings in the season. If the season has no ratings yet, RatingKit snapshots the default algorithm. Pass `false` to skip snapshots:

```php
RatingKit::closeSeason($season, snapshot: false);
```
