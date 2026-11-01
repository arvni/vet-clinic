<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * Jalali conversion at the edges only. Storage and date arithmetic
 * (reminders, "14 days before") stay Gregorian UTC.
 */
final class JalaliDate
{
    public const TIMEZONE = 'Asia/Tehran';

    /** Format a UTC instant as a Jalali date in Tehran time, e.g. 1405/08/10. */
    public static function format(CarbonInterface $instant, string $pattern = 'Y/m/d', ?string $locale = null): string
    {
        $local = $instant->copy()->setTimezone(self::TIMEZONE);
        $formatted = Jalalian::fromCarbon(Carbon::instance($local))->format($pattern);

        return ($locale ?? app()->getLocale()) === 'fa' ? Digits::toPersian($formatted) : $formatted;
    }

    /** Parse a Jalali date (Y/m/d, Persian or Latin digits) as Tehran midnight, returned in UTC. */
    public static function parse(string $jalali): Carbon
    {
        $normalized = str_replace('-', '/', Digits::toLatin(trim($jalali)));

        $carbon = Jalalian::fromFormat('Y/m/d', $normalized, new \DateTimeZone(self::TIMEZONE))->toCarbon();

        return Carbon::instance($carbon)->startOfDay()->utc();
    }
}
