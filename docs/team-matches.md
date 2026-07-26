# 👥 Team and Multiplayer Matches

## 🤝 2v2

```php
RatingKit::teamVsTeam(
    left: [$a, $b],
    right: [$c, $d],
    result: 'left',
    algorithm: 'glicko2',
    pool: 'chess.teams.2v2',
);
```

## 🛡️ 3v3

```php
RatingKit::teamVsTeam(
    left: [$a, $b, $c],
    right: [$d, $e, $f],
    result: 'right',
    algorithm: 'weng_lin',
    pool: 'arena.3v3',
);
```

## 🔢 Arbitrary N-vs-N

No fixed team-size ceiling is enforced:

```php
RatingKit::teamVsTeam(
    left: $tenPlayersA,
    right: $tenPlayersB,
    result: 'draw',
    algorithm: 'elo',
    pool: 'war-game.10v10',
);
```

The teams do not need to contain the same number of players. Weighted-average aggregation is the default and is useful for uneven team sizes.

## 🌐 More Than Two Teams

```php
use EloquentWorks\RatingKit\Data\RecordMatch;
use EloquentWorks\RatingKit\Data\Team;

RatingKit::record(new RecordMatch(
    teams: [
        new Team([$redA, $redB], rank: 1, score: 20),
        new Team([$blueA, $blueB], rank: 2, score: 17),
        new Team([$greenA, $greenB], rank: 2, score: 17),
        new Team([$goldA, $goldB], rank: 4, score: 8),
    ],
    algorithm: 'plackett_luce',
    pool: 'arena.four-teams',
));
```

Blue and Green share rank 2, representing a tie.

## 🥇 Free-for-All

```php
RatingKit::freeForAll([
    ['participant' => $a, 'rank' => 1],
    ['participant' => $b, 'rank' => 2],
    ['participant' => $c, 'rank' => 3],
]);
```

Each placement becomes a one-player team internally.

## ⚖️ Partial Participation

```php
use EloquentWorks\RatingKit\Data\Participant;

new Team([
    new Participant($starter, 1.0),
    new Participant($substitute, 0.4),
], rank: 1);
```

A weight must be greater than zero. Weight values are relative within the team.

## 🚫 Duplicate Participants

A persisted model may appear only once in a match. RatingKit rejects:

- A player listed twice on one team
- A player listed on opposing teams
- A player listed on two separate teams in a multi-team result
