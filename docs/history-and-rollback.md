# 🧾 History, Adjustments, and Rollback

## 📈 Read History

```php
$history = $user->ratingHistory('chess.blitz', 'glicko2');
```

History reasons include:

- `match`
- `match_voided`
- `manual_adjustment`
- `manual_set`
- `manual_reset`
- `inactivity_decay`
- Application-defined reasons

## 🏔️ Peak Rating

```php
$peak = $user->peakRating('chess.blitz', 'glicko2');
```

## ➕ Manual Adjustment

```php
RatingKit::adjust(
    model: $user,
    amount: 15,
    reason: 'event_bonus',
    pool: 'arena.ranked',
    algorithm: 'glicko2',
    metadata: ['event_id' => 44],
);
```

## 🎯 Set an Exact Rating

```php
RatingKit::setRating(
    model: $user,
    value: 1750,
    reason: 'legacy_import',
);
```

## 🔄 Reset

```php
RatingKit::resetRating($user);
```

## ↩️ Void a Match

```php
RatingKit::void($match, 'Duplicate result');
```

A direct rollback is safe only when the match is the latest recorded result for every participant. If any participant has a later result, RatingKit throws `UnsafeRollback` instead of corrupting the rating chain.

For an older match, void it in the application's source-of-truth match system and rebuild that pool in chronological order using the stored result data.
