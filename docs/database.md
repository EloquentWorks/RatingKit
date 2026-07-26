# 🗃️ Database

RatingKit uses seven tables.

## 📅 `rating_kit_seasons`

Stores season names, slugs, pool association, dates, closure time, and metadata.

## 📈 `rating_kit_ratings`

Stores the current state for each rateable model, pool, algorithm, and season. Rateable IDs can use numeric, UUID, or ULID morph columns selected before migrations are published.

Important fields:

- `rating`
- `deviation`
- `volatility`
- `games`
- `wins`
- `draws`
- `losses`
- `streak`
- `provisional`
- `last_competed_at`

A unique identity index prevents duplicate rating records.

## 🏁 `rating_kit_matches`

Stores match identity, pool, algorithm, the exact algorithm-option snapshot, season, status, occurrence time, external ID, and void information.

## 👥 `rating_kit_teams`

Stores team position, rank, score, name, and metadata for each match.

## 🧍 `rating_kit_participants`

Stores participant identity, team, outcome, weight, complete before-and-after rating state, and delta.

## 🧾 `rating_kit_histories`

Stores the audit timeline for matches, decay, manual adjustments, resets, and rollback.

## 📸 `rating_kit_leaderboard_snapshots`

Stores immutable JSON leaderboard captures.

## 🛠️ Custom Table Names

Change the `tables` array before publishing migrations. Existing installations require explicit database renames and configuration changes together.
