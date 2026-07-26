# 🔮 Match Predictions

RatingKit can calculate normalized estimated winning shares for two or more teams.

```php
use EloquentWorks\RatingKit\Data\Team;

$prediction = RatingKit::predict([
    new Team([$a, $b], rank: 1),
    new Team([$c, $d], rank: 2),
], pool: 'arena.2v2', algorithm: 'glicko2');
```

Example result:

```php
[
    ['team' => 1, 'rating' => 1600.0, 'probability' => 0.64],
    ['team' => 2, 'rating' => 1500.0, 'probability' => 0.36],
]
```

The prediction helper is intentionally algorithm-neutral and uses normalized team strength. Treat it as an estimate for matchmaking and UI display, not as a guaranteed calibrated probability for every domain.


## ⚖️ Match Quality

Convert the prediction into a normalized balance score:

```php
$quality = RatingKit::matchQuality([
    new Team([$a, $b], rank: 1),
    new Team([$c, $d], rank: 2),
], pool: 'arena.2v2', algorithm: 'glicko2');
```

A score near `1.0` represents a balanced match. A score near `0.0` represents a heavily one-sided match. The helper works with two or more teams.
