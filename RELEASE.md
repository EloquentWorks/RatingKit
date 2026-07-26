# Laravel RatingKit v1.0.0 🎉

The first stable release of Laravel RatingKit.

Laravel RatingKit provides a comprehensive Laravel-native rating engine for individual competitors, teams, multi-team matches, free-for-alls, seasons, leaderboards, histories, predictions, and pluggable rating algorithms.

## ✨ Features

- Fourteen bundled algorithms
- 1v1, 2v2, 3v3, and arbitrary N-vs-N support
- Multiple teams per match
- Free-for-all placement ratings
- Ties and shared ranks
- Weighted participants and substitutes
- Polymorphic `HasRatings` trait
- Per-model rating statistics, ranks, histories, matches, and adjustment helpers
- Separate rating pools
- Seasonal ratings with per-algorithm final snapshots
- Provisional ratings
- Deviation and volatility tracking
- Rating history
- Safe latest-match rollback
- External-ID idempotency
- Tie-aware leaderboards and snapshots
- Conservative ranking
- Match prediction and quality scoring
- Pool statistics
- Direction-aware recurring inactivity decay
- Manual adjustments and resets
- Transactional processing and row locking
- Lifecycle events
- Custom algorithms
- Numeric, UUID, and ULID rateable keys
- Per-match algorithm option snapshots
- Complete documentation and CI coverage

## 🧮 Algorithms

- Elo
- Margin-of-victory Elo
- FIDE-style Elo
- USCF-style Elo
- Glicko
- Glicko-2
- Weng-Lin / OpenSkill-style
- Bradley-Terry
- Plackett-Luce
- Thurstone-Mosteller
- DWZ-style
- EGF-style
- Ingo-style
- FIFA-style Elo

## 🚀 Installation

```bash
composer require eloquent-works/rating-kit
php artisan rating-kit:install --migrate
```

## 🧰 Quality Checks

```bash
composer quality
```
