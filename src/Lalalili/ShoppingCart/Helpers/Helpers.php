<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Helpers;

class Helpers
{
    public static function normalizePrice(mixed $price): mixed
    {
        if (is_string($price) && is_numeric($price)) {
            return (float) $price;
        }

        return $price;
    }

    public static function toString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }

    public static function toInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public static function toFloat(mixed $value, float $default = 0.0): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * @param array<int|string, mixed> $array
     */
    public static function isMultiArray(array $array, bool $recursive = false): bool
    {
        if ($recursive) {
            return count($array) !== count($array, COUNT_RECURSIVE);
        }

        foreach ($array as $value) {
            return is_array($value);
        }

        return false;
    }

    public static function issetAndHasValueOrAssignDefault(mixed &$var, mixed $default = false): mixed
    {
        if (isset($var) && $var !== '') {
            return $var;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function formatValue(float|int $value, bool $formatNumbers, array $config): float|int|string
    {
        if ($formatNumbers && (bool) ($config['format_numbers'] ?? false)) {
            return number_format(
                (float) $value,
                self::toInt($config['decimals'] ?? 0),
                self::toString($config['dec_point'] ?? '.', '.'),
                self::toString($config['thousands_sep'] ?? ',', ',')
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function roundingRule(array $config, string $key): mixed
    {
        $rounding = $config['rounding'] ?? [];

        if (!is_array($rounding)) {
            return null;
        }

        return $rounding[$key] ?? null;
    }

    /**
     * @param mixed $rule false|null|int|array{precision?: int|numeric-string, mode?: int|string}
     */
    public static function roundValue(float|int $value, mixed $rule): float|int
    {
        if ($rule === null || $rule === false) {
            return $value;
        }

        $precision = 0;
        $mode = PHP_ROUND_HALF_UP;

        if (is_int($rule)) {
            $precision = $rule;
        } elseif (is_array($rule)) {
            $precision = self::toInt($rule['precision'] ?? 0);
            $mode = self::resolveRoundingMode($rule['mode'] ?? PHP_ROUND_HALF_UP);
        } else {
            return $value;
        }

        return round((float) $value, $precision, $mode);
    }

    /**
     * @return PHP_ROUND_HALF_UP|PHP_ROUND_HALF_DOWN|PHP_ROUND_HALF_EVEN|PHP_ROUND_HALF_ODD
     */
    private static function resolveRoundingMode(mixed $mode): int
    {
        if (is_int($mode)) {
            return match ($mode) {
                PHP_ROUND_HALF_DOWN => PHP_ROUND_HALF_DOWN,
                PHP_ROUND_HALF_EVEN => PHP_ROUND_HALF_EVEN,
                PHP_ROUND_HALF_ODD => PHP_ROUND_HALF_ODD,
                default => PHP_ROUND_HALF_UP,
            };
        }

        if (is_string($mode)) {
            return match ($mode) {
                'half_down' => PHP_ROUND_HALF_DOWN,
                'half_even' => PHP_ROUND_HALF_EVEN,
                'half_odd' => PHP_ROUND_HALF_ODD,
                default => PHP_ROUND_HALF_UP,
            };
        }

        return PHP_ROUND_HALF_UP;
    }
}
