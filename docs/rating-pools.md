# 🗂️ Rating Pools

A pool isolates one rating population from another.

## ♟️ Chess Example

```text
chess.standard
chess.rapid
chess.blitz
chess.bullet
chess.teams
chess.atomic
chess.chess960
```

## 🎮 Game Queue Example

```text
arena.casual.2v2
arena.ranked.2v2
arena.ranked.3v3
arena.ranked.5v5
```

## 🌍 Regional Example

```text
football.na
football.eu
football.apac
```

## 🧬 Independent Algorithm Records

Pool and algorithm both form part of a rating identity:

```php
$user->ratingFor('chess.blitz', 'elo');
$user->ratingFor('chess.blitz', 'glicko2');
```

These are separate records.

## 🏷️ Naming Guidance

Use stable machine-readable names. Dot-delimited names are convenient but not required.

Avoid renaming an active pool without migrating its database records, match records, and snapshots together.
