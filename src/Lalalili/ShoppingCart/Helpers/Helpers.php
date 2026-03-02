<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Helpers;

class Helpers
{
    public static function normalizePrice(mixed $price): mixed
    {
        return is_string($price) ? (float) $price : $price;
    }

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

    public static function formatValue(float|int $value, bool $formatNumbers, array $config): float|int|string
    {
        if ($formatNumbers && (bool) ($config['format_numbers'] ?? false)) {
            return number_format(
                (float) $value,
                (int) ($config['decimals'] ?? 0),
                (string) ($config['dec_point'] ?? '.'),
                (string) ($config['thousands_sep'] ?? ',')
            );
        }

        return $value;
    }
}
