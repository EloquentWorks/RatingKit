# 🧰 Artisan Commands

## 🛠️ Install

```bash
php artisan rating-kit:install
php artisan rating-kit:install --migrate
```

## 💤 Apply Inactivity Decay

```bash
php artisan rating-kit:decay
php artisan rating-kit:decay --pool=chess.blitz
php artisan rating-kit:decay --algorithm=glicko2
```

Decay performs no changes while `rating-kit.decay.enabled` is false. Eligible ratings are adjusted at most once per configured decay period, and the direction automatically respects higher-is-better or lower-is-better algorithms.

## 📸 Capture a Leaderboard

```bash
php artisan rating-kit:snapshot \
    --pool=chess.blitz \
    --algorithm=glicko2 \
    --season=4 \
    --limit=100
```

## 🔒 Close a Season

```bash
php artisan rating-kit:close-season summer-2026
php artisan rating-kit:close-season 4 --no-snapshot
```

## ⏰ Scheduling

```php
use Illuminate\Console\Scheduling\Schedule;

protected function schedule(Schedule $schedule): void
{
    $schedule->command('rating-kit:decay')->daily();
    $schedule->command('rating-kit:snapshot --pool=chess.blitz --algorithm=glicko2')->dailyAt('00:05');
}
```
