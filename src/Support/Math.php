<?php

namespace EloquentWorks\RatingKit\Support;

/**
 * Class Math
 *
 * Provides mathematical utility functions for rating calculations.
 */
final class Math
{
    /**
     * Private constructor to prevent instantiation of the Math class.
     */
    private function __construct() {}

    /**
     * Computes the logistic function for a given difference and scale.
     *
     * @param  float  $difference  The difference value to compute the logistic function for.
     * @param  float  $scale  The scale factor for the logistic function (default is 400.0).
     * @return float The result of the logistic function.
     */
    public static function logistic(float $difference, float $scale = 400.0): float
    {
        // Clamp the exponent to avoid overflow in the exponential calculation
        $exponent = max(-300.0, min(300.0, -$difference / max($scale, 0.000001)));

        // Compute the logistic function using the clamped exponent
        return 1.0 / (1.0 + 10.0 ** $exponent);
    }

    /**
     * Computes the cumulative distribution function (CDF) of the standard normal distribution for a given value.
     *
     * @param  float  $value  The value to compute the CDF for.
     * @return float The result of the normal CDF.
     */
    public static function normalCdf(float $value): float
    {
        // Use the error function approximation to compute the normal CDF
        $sign = $value < 0 ? -1.0 : 1.0;
        $x = abs($value) / sqrt(2.0);
        $t = 1.0 / (1.0 + 0.3275911 * $x);
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $erf = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x);

        // Return the CDF value using the error function approximation
        return 0.5 * (1.0 + $sign * $erf);
    }

    /**
     * Clamps a value between a minimum and maximum value.
     *
     * @param  float  $value  The value to clamp.
     * @param  float|null  $minimum  The minimum value (optional).
     * @param  float|null  $maximum  The maximum value (optional).
     * @return float The clamped value.
     */
    public static function clamp(float $value, ?float $minimum = null, ?float $maximum = null): float
    {
        // Clamp the value to the specified minimum and maximum bounds
        if ($minimum !== null) {
            $value = max($minimum, $value);
        }

        // Clamp the value to the specified maximum bound
        if ($maximum !== null) {
            $value = min($maximum, $value);
        }

        // Return the clamped value
        return $value;
    }
}
