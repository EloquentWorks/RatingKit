# ⚙️ Configuration

Publish the file:

```bash
php artisan vendor:publish --tag=rating-kit-config
```

## 🧮 Defaults

```php
'default_algorithm' => 'glicko2',
'default_pool' => 'default',

'initial' => [
    'rating' => 1500.0,
    'deviation' => 350.0,
    'volatility' => 0.06,
],
```

## 🐣 Provisional Ratings

```php
'provisional_games' => 10,
```

A rating becomes established after the configured number of processed games.

## 🧱 Floors and Ceilings

```php
'rating_floor' => null,
'rating_ceiling' => null,
```

Set numeric values to clamp algorithm and manual changes.

## 🧾 History and Events

```php
'history_enabled' => true,
'dispatch_events' => true,
```

## 🪪 Rateable Key Type

Choose the key type used by models receiving ratings before publishing migrations:

```php
'morph_key_type' => 'numeric',
```

Supported values are `numeric`, `uuid`, and `ulid`.

## 👥 Team Behavior

```php
'team_aggregation' => 'average',
'team_distribution' => 'participation',
```

Aggregation values:

- `average`
- `sum`

Distribution values for pairwise drivers:

- `equal`
- `participation`
- `rating_weighted`

## ↕️ Algorithm Direction

```php
'algorithm_directions' => [
    'ingo' => 'asc',
],
```

Use `desc` for higher-is-better and `asc` for lower-is-better.

## 🧩 Algorithm Registry

```php
'algorithms' => [
    'elo' => EloAlgorithm::class,
    'glicko2' => Glicko2Algorithm::class,
    'custom' => App\Ratings\CustomAlgorithm::class,
],
```

## 💤 Inactivity Decay

```php
'decay' => [
    'enabled' => false,
    'inactive_after_days' => 90,
    'period_days' => 30,
    'points_per_period' => 10.0,
    'minimum_rating' => 1200.0,
    'maximum_rating' => 2200.0,
],
```

Each rating can decay only once per `period_days`. Higher-is-better algorithms move toward `minimum_rating`; lower-is-better algorithms move toward `maximum_rating`.

## 🗃️ Tables and Models

All table names and model classes are replaceable through the `tables` and `models` arrays.
