<?php

namespace App\Helpers;

use Carbon\Carbon;

class Format
{
    public static function number(int|float $value, int $decimals = 0): string
    {
        if (app()->getLocale() === 'da') {
            return number_format($value, $decimals, ',', '.');
        }

        return number_format($value, $decimals, '.', ',');
    }

    public static function currency(float $value, int $decimals = 2): string
    {
        return '$' . self::number($value, $decimals);
    }

    public static function dateTime(?Carbon $dt, string $format = 'short'): string
    {
        if (! $dt) {
            return '-';
        }

        return match ($format) {
            'time' => $dt->format('H:i:s'),
            'short' => self::isDA()
                ? $dt->format('d/m/Y H:i:s')
                : $dt->format('Y-m-d H:i:s'),
            'date_time_short' => self::isDA()
                ? $dt->format('d/m H:i')
                : $dt->format('m/d H:i'),
            default => $dt->format($format),
        };
    }

    public static function relative(?Carbon $dt): string
    {
        if (! $dt) {
            return '-';
        }

        Carbon::setLocale(app()->getLocale());

        return $dt->diffForHumans();
    }

    private static function isDA(): bool
    {
        return app()->getLocale() === 'da';
    }
}
