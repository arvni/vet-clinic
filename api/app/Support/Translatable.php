<?php

declare(strict_types=1);

namespace App\Support;

/**
 * For models with `<field>_fa` / `<field>_en` columns. English falls back to
 * Persian when empty (spec: Localization).
 */
trait Translatable
{
    public function translated(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = $this->getAttribute("{$field}_{$locale}");

        return ($value === null || $value === '') ? $this->getAttribute("{$field}_fa") : $value;
    }

    public function name(?string $locale = null): ?string
    {
        return $this->translated('name', $locale);
    }
}
