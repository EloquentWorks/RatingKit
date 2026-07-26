# 🧮 Rating Algorithms

RatingKit ships with fourteen drivers. Every bundled driver supports teams and multiple teams through RatingKit's normalized match representation.

## 📋 Algorithm Matrix

| Key | Rating | Deviation | Volatility | Team support | Multi-team support |
|---|---:|---:|---:|---:|---:|
| `elo` | ✅ | — | — | ✅ | ✅ |
| `elo_mov` | ✅ | — | — | ✅ | ✅ |
| `fide` | ✅ | — | — | ✅ | ✅ |
| `uscf` | ✅ | — | — | ✅ | ✅ |
| `glicko` | ✅ | ✅ | — | ✅ | ✅ |
| `glicko2` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `weng_lin` | ✅ | ✅ | — | ✅ | ✅ |
| `bradley_terry` | ✅ | — | — | ✅ | ✅ |
| `plackett_luce` | ✅ | — | — | ✅ | ✅ |
| `thurstone_mosteller` | ✅ | — | — | ✅ | ✅ |
| `dwz` | ✅ | — | — | ✅ | ✅ |
| `egf` | ✅ | — | — | ✅ | ✅ |
| `ingo` | ✅ | — | — | ✅ | ✅ |
| `fifa` | ✅ | — | — | ✅ | ✅ |

## 🎯 Selection Guide

### Elo

Use `elo` when you need a familiar, predictable rating change and do not need uncertainty tracking.

### Margin-of-Victory Elo

Use `elo_mov` when scores are available and a larger winning margin should generally create a larger update.

### Glicko-2

Use `glicko2` for chess servers, ladders, and competitive queues where uncertainty and volatility matter.

### Weng-Lin

Use `weng_lin` for team games, uneven teams, substitutes, and multiplayer environments that benefit from Bayesian uncertainty.

### Plackett-Luce

Use `plackett_luce` for free-for-alls, races, battle royale placements, and matches containing several ranked teams.

### Thurstone-Mosteller

Use `thurstone_mosteller` when a Gaussian performance model fits the domain.

## ⚙️ Algorithm Options

Edit `algorithm_options` in `config/rating-kit.php`:

```php
'algorithm_options' => [
    'elo' => [
        'k_factor' => 32.0,
        'scale' => 400.0,
    ],
    'glicko2' => [
        'tau' => 0.5,
        'epsilon' => 0.000001,
    ],
    'weng_lin' => [
        'beta' => 200.0,
    ],
],
```

## 👥 Team Aggregation

```php
'team_aggregation' => 'average',
```

Supported values:

- `average`: weighted average team rating
- `sum`: weighted sum of team ratings

`average` is usually preferable when teams of different sizes can compete.

## ⚖️ Team Distribution

Elo-family and pairwise drivers can distribute a team update using:

```php
'team_distribution' => 'participation',
```

Supported values:

- `equal`
- `participation`
- `rating_weighted`

Bayesian drivers use each competitor's uncertainty as part of their update.

## ⚠️ Federation-Style Drivers

`fide`, `uscf`, `dwz`, `egf`, `ingo`, and `fifa` are configurable practical adaptations for application use. They are not assertions of federation certification, and an application with formal federation requirements should verify every rule against the applicable current regulations.


## ↕️ Rating Direction

Most algorithms use higher-is-better ratings. The Ingo-style driver uses lower-is-better values. RatingKit applies the configured direction to leaderboards, individual ranks, conservative scores, predictions, and peak/best rating helpers.

```php
'algorithm_directions' => [
    'ingo' => 'asc',
],
```
