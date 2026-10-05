<?php

namespace App\Support;

/**
 * Consistent number formatting for the dashboard and exports.
 * Numbers are shown with thousands separators (IDR style: 1,984,774),
 * percentages to one decimal, and a dash for missing data (never #REF!/#DIV/0!).
 */
class Format
{
    public const EMPTY = '–';

    /** Money without decimals, e.g. 1,984,774. */
    public static function idr(int|float|null $value): string
    {
        if ($value === null) {
            return self::EMPTY;
        }

        return number_format((float) $value, 0, '.', ',');
    }

    /** Money with the IDR prefix, e.g. "IDR 1,984,774". */
    public static function idrPrefixed(int|float|null $value): string
    {
        if ($value === null) {
            return self::EMPTY;
        }

        return 'IDR '.self::idr($value);
    }

    /** Percentage to one decimal, e.g. 72.5%. */
    public static function percent(int|float|null $value, int $decimals = 1): string
    {
        if ($value === null) {
            return self::EMPTY;
        }

        return number_format((float) $value, $decimals, '.', ',').'%';
    }

    /** Plain integer with separators, e.g. 1,240. */
    public static function number(int|float|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return self::EMPTY;
        }

        return number_format((float) $value, $decimals, '.', ',');
    }

    /**
     * Variance between actual and a comparison (budget or last year).
     * Returns ['pct' => float|null, 'direction' => 'up'|'down'|'flat'].
     */
    public static function variance(int|float|null $actual, int|float|null $compare): array
    {
        if ($actual === null || $compare === null || (float) $compare == 0.0) {
            return ['pct' => null, 'direction' => 'flat'];
        }

        $pct = (($actual - $compare) / abs($compare)) * 100;
        $direction = $pct > 0.05 ? 'up' : ($pct < -0.05 ? 'down' : 'flat');

        return ['pct' => $pct, 'direction' => $direction];
    }
}
