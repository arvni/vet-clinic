<?php

declare(strict_types=1);

namespace App\Support;

final class PersianText
{
    /**
     * Normalize text for storage and search: Arabic yeh/kaf to Persian,
     * strip tatweel and zero-width joiners used as decoration, unify digits,
     * collapse whitespace.
     */
    public static function normalize(string $value): string
    {
        $value = strtr($value, [
            'ي' => 'ی',
            'ى' => 'ی',
            'ك' => 'ک',
            'ة' => 'ه',
            'ـ' => '',
        ]);

        $value = Digits::toLatin($value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
