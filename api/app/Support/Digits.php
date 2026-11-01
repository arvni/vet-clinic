<?php

declare(strict_types=1);

namespace App\Support;

final class Digits
{
    private const PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /** Convert Persian and Arabic-Indic digits to ASCII. Apply to all numeric input. */
    public static function toLatin(string $value): string
    {
        return str_replace(self::ARABIC, self::LATIN, str_replace(self::PERSIAN, self::LATIN, $value));
    }

    public static function toPersian(string $value): string
    {
        return str_replace(self::LATIN, self::PERSIAN, $value);
    }
}
