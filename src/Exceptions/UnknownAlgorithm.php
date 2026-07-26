<?php

namespace EloquentWorks\RatingKit\Exceptions;

use InvalidArgumentException;

/**
 * Class UnknownAlgorithm
 *
 * Exception thrown when an unregistered rating algorithm is requested.
 */
class UnknownAlgorithm extends InvalidArgumentException
{
    /**
     * Create a new UnknownAlgorithm exception for the given algorithm name.
     *
     * @param string $algorithm The name of the unregistered algorithm
     *
     * @return self
     */
    public static function named(string $algorithm): self
    {
        return new self("The rating algorithm [{$algorithm}] is not registered.");
    }
}
