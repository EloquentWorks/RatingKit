# 📣 Events

RatingKit dispatches lifecycle events after successful transaction commits.

## 🏁 Match Events

- `MatchRated`
- `MatchVoided`

## 📈 Rating Events

- `RatingUpdated`
- `RatingAdjusted`

## 📅 Season Events

- `SeasonClosed`

## 📸 Leaderboard Events

- `LeaderboardSnapshotted`

## 👂 Listener Example

```php
use EloquentWorks\RatingKit\Events\MatchRated;

class PublishMatchRating
{
    public function handle(MatchRated $event): void
    {
        $match = $event->match;
    }
}
```

## 🔕 Disable Events

```php
'dispatch_events' => false,
```
