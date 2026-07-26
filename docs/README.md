# Laravel RatingKit Documentation 🏆

Laravel RatingKit is a Laravel-native rating engine for individual competitors, teams, free-for-alls, multiple-team matches, seasons, leaderboards, and rating history.

## 🚀 Start Here

1. [Install RatingKit](installation.md)
2. [Complete the quick start](quick-start.md)
3. [Add the HasRatings trait](has-ratings.md)
4. [Choose an algorithm](algorithms.md)
5. [Record team or multiplayer matches](team-matches.md)
6. [Build a leaderboard](leaderboards.md)

## 📚 Documentation Index

| Guide | Description |
|---|---|
| [⚙️ Installation](installation.md) | Composer installation, publishing, migrations, and model setup |
| [⚡ Quick Start](quick-start.md) | First 1v1, team, and free-for-all matches |
| [🧬 HasRatings](has-ratings.md) | Model relationships, statistics, ranks, history, matches, and adjustments |
| [🧮 Algorithms](algorithms.md) | Bundled algorithms, selection guidance, and options |
| [👥 Team Matches](team-matches.md) | 2v2, 3v3, N-vs-N, multi-team, ties, and weights |
| [🗂️ Rating Pools](rating-pools.md) | Separate ratings for games, variants, queues, and time controls |
| [📅 Seasons](seasons.md) | Seasonal ratings and final snapshots |
| [📊 Leaderboards](leaderboards.md) | Shared ranks, pool statistics, conservative scores, and snapshots |
| [🔮 Predictions](predictions.md) | Estimated winning shares and match-quality scoring |
| [🧾 History and Rollback](history-and-rollback.md) | Audit history, voiding, and manual changes |
| [⚙️ Configuration](configuration.md) | Full configuration reference |
| [📣 Events](events.md) | Match, rating, season, and snapshot events |
| [🧰 Commands](commands.md) | Installation, decay, snapshots, and season closing |
| [🧩 Custom Algorithms](custom-algorithms.md) | Implement and register custom rating drivers |
| [🗃️ Database](database.md) | Tables, fields, indexes, and customization |
| [🏗️ Architecture](architecture.md) | Data flow, transactions, locks, and extension points |
| [🧪 Testing](testing.md) | Package and application testing examples |
| [📖 API Reference](api-reference.md) | Manager, trait, data objects, and model APIs |
| [⬆️ Upgrade Guide](upgrade-guide.md) | Safe upgrade practices |
| [❓ FAQ](faq.md) | Common design and integration questions |

## 🧭 Common Paths

### Chess server

Use Glicko-2 with separate pools for standard, rapid, blitz, variants, and teams:

```php
RatingKit::oneVsOne($white, $black, 'left', 'glicko2', 'chess.blitz');
RatingKit::teamVsTeam($redYellow, $blueGreen, 'left', 'glicko2', 'chess.4pc.teams');
```

### Team game

Use Weng-Lin or Glicko-2 for arbitrary team sizes:

```php
RatingKit::teamVsTeam($squadA, $squadB, 'right', 'weng_lin', 'arena.5v5');
```

### Battle royale

Use Plackett-Luce for ordered placements:

```php
RatingKit::freeForAll($placements, 'plackett_luce', 'battle-royale.solo');
```

### Sports league

Use margin-of-victory Elo when score difference should matter:

```php
RatingKit::record($scoredMatch);
```

## 🔗 Main Package README

Return to the [Laravel RatingKit README](../README.md).
