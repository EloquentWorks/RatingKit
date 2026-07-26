# ⚙️ Installation

## 📦 Composer

```bash
composer require eloquent-works/rating-kit
```

## 🛠️ Install Resources

```bash
php artisan rating-kit:install --migrate
```

This publishes:

- `config/rating-kit.php`
- RatingKit migrations

Run the steps separately when preferred:

```bash
php artisan vendor:publish --tag=rating-kit-config
php artisan vendor:publish --tag=rating-kit-migrations
php artisan migrate
```

## 🧬 Add the Trait

Any Eloquent model that can hold a rating should use `HasRatings`:

```php
use EloquentWorks\RatingKit\Traits\HasRatings;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasRatings;
}
```

The trait provides rating relationships, current-rating helpers, provisional checks, peak ratings, and history access.

## ✅ Verify Installation

```bash
php artisan list | grep rating-kit
```

Expected commands include:

```text
rating-kit:install
rating-kit:decay
rating-kit:snapshot
rating-kit:close-season
```

Run package quality checks in development:

```bash
composer quality
```

## 🗂️ Multiple Rateable Models

RatingKit uses polymorphic relationships. Users, teams, bots, clubs, or any other persisted Eloquent model can be rated:

```php
class Bot extends Model
{
    use HasRatings;
}

class Club extends Model
{
    use HasRatings;
}
```
