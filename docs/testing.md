# 🧪 Testing

## ✅ Package Tests

```bash
composer test
composer analyse
composer format:test
composer quality
```

## 🧍 Create Test Competitors

```php
$alpha = User::factory()->create();
$bravo = User::factory()->create();
```

## 🏁 Assert a Match

```php
$match = RatingKit::oneVsOne($alpha, $bravo, 'left', 'elo', 'test');

$this->assertSame('processed', $match->status->value);
$this->assertGreaterThan(1500, $alpha->currentRating('test', 'elo'));
$this->assertLessThan(1500, $bravo->currentRating('test', 'elo'));
```

## 👥 Assert 3v3

```php
$match = RatingKit::teamVsTeam(
    $redPlayers,
    $bluePlayers,
    'left',
    'glicko2',
    'test.3v3',
);

$this->assertCount(6, $match->participants);
```

## 📣 Fake Events

```php
Event::fake([MatchRated::class]);

RatingKit::oneVsOne($alpha, $bravo, 'left');

Event::assertDispatched(MatchRated::class);
```

## 🆔 Test Idempotency

Record the same `externalId` twice and assert that ratings change only once.
