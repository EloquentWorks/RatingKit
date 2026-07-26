# 🧩 Custom Algorithms

## 📜 Implement the Contract

```php
namespace App\Ratings;

use EloquentWorks\RatingKit\Contracts\RatingAlgorithm;
use EloquentWorks\RatingKit\Data\MatchInput;
use EloquentWorks\RatingKit\Data\RatingBatch;

class CustomAlgorithm implements RatingAlgorithm
{
    public function __construct(private array $options = []) {}

    public function key(): string
    {
        return 'custom';
    }

    public function supportsTeams(): bool
    {
        return true;
    }

    public function supportsMultipleTeams(): bool
    {
        return true;
    }

    public function rate(MatchInput $match): RatingBatch
    {
        // Return one RatingChange for every competitor key.
    }
}
```

## ⚙️ Register in Configuration

```php
'algorithms' => [
    'custom' => App\Ratings\CustomAlgorithm::class,
],
```

## 🔌 Register at Runtime

```php
RatingKit::algorithms()->extend('custom', CustomAlgorithm::class);
```

A closure resolver may use the container and configured options:

```php
RatingKit::algorithms()->extend(
    'custom',
    fn ($app, array $options) => new CustomAlgorithm($options),
);
```

## ✅ Driver Requirements

A driver must:

- Return a change for every competitor
- Preserve each competitor key
- Return finite rating values
- Declare team and multi-team support accurately
- Avoid mutating input data objects
- Produce deterministic output for identical input and options
