<?php

namespace EloquentWorks\RatingKit\Support;

use EloquentWorks\RatingKit\Contracts\RatingAlgorithm;
use EloquentWorks\RatingKit\Exceptions\UnknownAlgorithm;
use Illuminate\Contracts\Container\Container;

/**
 * This class manages the registration and resolution of rating algorithms.
 */
class AlgorithmRegistry
{
    /** @var array<string, class-string<RatingAlgorithm>|RatingAlgorithm|callable(Container, array<string, mixed>): RatingAlgorithm> */
    protected array $extensions = [];

    /**
     * Create a new AlgorithmRegistry instance.
     *
     * @param Container $container The container instance for resolving dependencies.
     */
    public function __construct(protected Container $container) {}

    /**
     * @param class-string<RatingAlgorithm>|RatingAlgorithm|callable(Container, array<string, mixed>): RatingAlgorithm $algorithm
     */
    public function extend(string $key, string|RatingAlgorithm|callable $algorithm): void
    {
        $this->extensions[$key] = $algorithm;
    }

    /**
     * Resolve a rating algorithm by its key.
     *
     * @param string|null $key The key of the rating algorithm to resolve. If null, the default algorithm will be used.
     *
     * @return RatingAlgorithm Returns the resolved rating algorithm instance.
     *
     * @throws UnknownAlgorithm If the specified algorithm key is not registered or configured.
     * @throws \LogicException If the resolved algorithm is not an instance of RatingAlgorithm.
     */
    public function resolve(?string $key = null): RatingAlgorithm
    {
        // If no key is provided, use the default algorithm key from the configuration.
        $key ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        $definition = $this->extensions[$key] ?? config("rating-kit.algorithms.{$key}");

        // If the definition is null, throw an UnknownAlgorithm exception.
        if ($definition === null) {
            throw UnknownAlgorithm::named($key);
        }

        // If the definition is already an instance of RatingAlgorithm, return it directly.
        if ($definition instanceof RatingAlgorithm) {
            return $definition;
        }

        // If the definition is a callable, invoke it with the container and options to resolve the algorithm.
        $options = $this->options($key);

        // If the definition is a callable, invoke it with the container and options to resolve the algorithm.
        if (is_callable($definition) && ! is_string($definition)) {
            $resolved = $definition($this->container, $options);

            // If the resolved value is not an instance of RatingAlgorithm, throw a LogicException.
            if (! $resolved instanceof RatingAlgorithm) {
                throw new \LogicException("The rating algorithm resolver [{$key}] did not return a RatingAlgorithm instance.");
            }

            return $resolved;
        }

        // If the definition is a string, it should be a class name. Use the container to make an instance of it.
        if (! is_string($definition) || ! is_a($definition, RatingAlgorithm::class, true)) {
            throw new \LogicException("The configured rating algorithm [{$key}] is invalid.");
        }

        // Use the container to make an instance of the algorithm class, passing in the options.
        $resolved = $this->container->make($definition, ['options' => $options]);

        // If the resolved value is not an instance of RatingAlgorithm, throw a LogicException.
        if (! $resolved instanceof RatingAlgorithm) {
            throw new \LogicException("The configured rating algorithm [{$key}] could not be resolved.");
        }

        // Return the resolved algorithm instance.
        return $resolved;
    }

    /**
     * Get the options for a specific rating algorithm.
     *
     * @param string|null $key The key of the rating algorithm. If null, the default algorithm will be used.
     *
     * @return array<string, mixed> Returns an associative array of options for the specified algorithm.
     */
    public function options(?string $key = null): array
    {
        $key ??= (string) config('rating-kit.default_algorithm', 'glicko2');
        /** @var array<string, mixed> $options */
        $options = (array) config("rating-kit.algorithm_options.{$key}", []);
        $options['rating_floor'] = config('rating-kit.rating_floor');
        $options['rating_ceiling'] = config('rating-kit.rating_ceiling');
        $options['team_aggregation'] = config('rating-kit.team_aggregation', 'average');
        $options['team_distribution'] = config('rating-kit.team_distribution', 'participation');

        // If the algorithm has a specific direction configured, include it in the options.
        return $options;
    }

    /**
     * Get the keys of all available rating algorithms.
     *
     * @return list<string> Returns a list of keys for all available rating algorithms.
     */
    public function keys(): array
    {
        // Get the keys of the configured algorithms from the configuration file and convert them to strings.
        $configured = array_map('strval', array_keys((array) config('rating-kit.algorithms', [])));
        $extended = array_map('strval', array_keys($this->extensions));

        // Merge the configured and extended algorithm keys, remove duplicates, and return the unique keys as a list.
        return array_values(array_unique(array_merge($configured, $extended)));
    }
}
