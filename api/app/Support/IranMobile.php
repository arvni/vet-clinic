<?php

declare(strict_types=1);

namespace App\Support;

final class IranMobile
{
    /**
     * Normalize an Iranian mobile number to E.164 (+989xxxxxxxxx).
     * Accepts 09…, 9…, +989…, 00989…, 989…, with Persian/Arabic digits,
     * spaces, dashes or parentheses. Returns null when invalid.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = (string) preg_replace('/[\s\-()]/', '', Digits::toLatin($input));

        if (preg_match('/^(?:\+98|0098|98|0)?(9\d{9})$/', $digits, $m) !== 1) {
            return null;
        }

        return '+98'.$m[1];
    }

    /** Local display form: 09121234567. */
    public static function toLocal(string $e164): string
    {
        return '0'.substr($e164, 3);
    }
}
