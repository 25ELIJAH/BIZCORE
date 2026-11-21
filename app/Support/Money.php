<?php

namespace App\Support;

/** Decimal-string money helpers (bcmath). Amounts are non-negative strings with 2 decimals. */
final class Money
{
    /** Round half up to 2 decimals (inputs are non-negative). */
    public static function round(string|int|float $amount): string
    {
        return bcadd(bcadd((string) $amount, '0.005', 3), '0', 2);
    }

    public static function mul(string|int|float $a, string|int|float $b): string
    {
        return self::round(bcmul((string) $a, (string) $b, 6));
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, 2);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, 2);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, 2);
    }

    public static function format(string|int|float $amount): string
    {
        return number_format((float) $amount, 2);
    }
}
