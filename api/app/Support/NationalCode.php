<?php

declare(strict_types=1);

namespace App\Support;

final class NationalCode
{
    /** Validate an Iranian national code (code-e melli) using its check digit. */
    public static function isValid(?string $input): bool
    {
        if ($input === null) {
            return false;
        }

        $code = Digits::toLatin(trim($input));

        if (preg_match('/^\d{10}$/', $code) !== 1 || preg_match('/^(\d)\1{9}$/', $code) === 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $code[9];

        return $remainder < 2 ? $check === $remainder : $check === 11 - $remainder;
    }
}
