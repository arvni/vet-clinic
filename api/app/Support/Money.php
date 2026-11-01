<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable money amount stored in current Iranian rials (integer).
 *
 * All persistence and arithmetic happen in rials; toman or the redenominated
 * unit are display concerns only, so the 2026 redenomination is a one-place
 * change in config('money.display_mode').
 */
final class Money implements JsonSerializable
{
    private const DIVISORS = [
        'rial' => 1,
        'toman' => 10,
        'new_rial' => 10_000,
    ];

    private function __construct(public readonly int $rials) {}

    public static function rials(int $rials): self
    {
        return new self($rials);
    }

    public static function toman(int $toman): self
    {
        return new self($toman * 10);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->rials + $other->rials);
    }

    public function minus(self $other): self
    {
        return new self($this->rials - $other->rials);
    }

    public function times(int $quantity): self
    {
        return new self($this->rials * $quantity);
    }

    /**
     * Percentage of this amount, rounded half-up to the nearest rial.
     * Basis points avoid float error: 10% = 1000 bp.
     */
    public function percent(int $basisPoints): self
    {
        return new self(intdiv($this->rials * $basisPoints + 5_000, 10_000));
    }

    public function isNegative(): bool
    {
        return $this->rials < 0;
    }

    public function equals(self $other): bool
    {
        return $this->rials === $other->rials;
    }

    public function format(?string $locale = null, ?string $mode = null): string
    {
        $mode ??= (string) config('money.display_mode', 'toman');
        $locale ??= app()->getLocale();

        if (! isset(self::DIVISORS[$mode])) {
            throw new InvalidArgumentException("Unknown money display mode [{$mode}].");
        }

        $value = intdiv($this->rials, self::DIVISORS[$mode]);
        $separator = $locale === 'fa' ? '٬' : ',';
        $number = number_format(abs($value), 0, '.', $separator);
        if ($value < 0) {
            $number = '-'.$number;
        }

        $number = $locale === 'fa' ? Digits::toPersian($number) : $number;

        return $number.' '.__("money.unit.{$mode}", [], $locale);
    }

    /**
     * API payloads carry rials as strings so JS clients never lose precision.
     *
     * @return array{rials: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'rials' => (string) $this->rials,
            'formatted' => $this->format(),
        ];
    }
}
