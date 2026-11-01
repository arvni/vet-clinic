<?php

use App\Support\Digits;
use App\Support\IranMobile;
use App\Support\JalaliDate;
use App\Support\Money;
use App\Support\NationalCode;
use App\Support\PersianText;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

describe('Money', function () {
    it('stores rials and converts from toman', function () {
        expect(Money::toman(296_000)->rials)->toBe(2_960_000);
    });

    it('does arithmetic in rials', function () {
        $total = Money::rials(1_000)->plus(Money::rials(500))->times(3)->minus(Money::rials(100));
        expect($total->rials)->toBe(4_400);
    });

    it('computes VAT with half-up rounding in basis points', function () {
        expect(Money::rials(12_345)->percent(1_000)->rials)->toBe(1_235);
    });

    it('formats toman in Persian and English', function () {
        expect(Money::rials(2_960_000)->format('fa', 'toman'))->toBe('۲۹۶٬۰۰۰ تومان')
            ->and(Money::rials(2_960_000)->format('en', 'toman'))->toBe('296,000 Toman');
    });

    it('formats the redenominated unit', function () {
        expect(Money::rials(2_960_000)->format('en', 'new_rial'))->toBe('296 new Rial');
    });

    it('serializes rials as a string', function () {
        expect(Money::rials(5)->jsonSerialize()['rials'])->toBe('5');
    });
});

describe('IranMobile', function () {
    it('normalizes common formats', function (string $input) {
        expect(IranMobile::normalize($input))->toBe('+989121234567');
    })->with(['09121234567', '۰۹۱۲۱۲۳۴۵۶۷', '+98 912 123 4567', '00989121234567', '989121234567', '9121234567', '0912-123-4567']);

    it('rejects invalid numbers', function (?string $input) {
        expect(IranMobile::normalize($input))->toBeNull();
    })->with(['02112345678', '0912123456', 'abc', '', null]);

    it('renders the local form', function () {
        expect(IranMobile::toLocal('+989121234567'))->toBe('09121234567');
    });
});

describe('Text helpers', function () {
    it('converts digits both ways', function () {
        expect(Digits::toLatin('۱۲۳٤٥'))->toBe('12345')
            ->and(Digits::toPersian('2026'))->toBe('۲۰۲۶');
    });

    it('normalizes Arabic letters to Persian', function () {
        expect(PersianText::normalize('  علي   كريمي  '))->toBe('علی کریمی');
    });

    it('validates national codes', function () {
        expect(NationalCode::isValid('1234567891'))->toBeTrue()
            ->and(NationalCode::isValid('۱۲۳۴۵۶۷۸۹۱'))->toBeTrue()
            ->and(NationalCode::isValid('1234567890'))->toBeFalse()
            ->and(NationalCode::isValid('1111111111'))->toBeFalse()
            ->and(NationalCode::isValid('123'))->toBeFalse();
    });
});

describe('JalaliDate', function () {
    it('formats a UTC instant in Tehran time', function () {
        // 2026-11-01 00:00 UTC is 03:30 Tehran on 10 Aban 1405.
        expect(JalaliDate::format(Carbon::parse('2026-11-01 00:00:00', 'UTC'), 'Y/m/d', 'en'))->toBe('1405/08/10')
            ->and(JalaliDate::format(Carbon::parse('2026-11-01 00:00:00', 'UTC'), 'Y/m/d', 'fa'))->toBe('۱۴۰۵/۰۸/۱۰');
    });

    it('handles the Tehran day boundary', function () {
        // 21:00 UTC on 31 Oct is already 00:30 on 1 Nov (10 Aban) in Tehran.
        expect(JalaliDate::format(Carbon::parse('2026-10-31 21:00:00', 'UTC'), 'Y/m/d', 'en'))->toBe('1405/08/10');
    });

    it('parses Jalali input with Persian digits to Tehran midnight in UTC', function () {
        expect(JalaliDate::parse('۱۴۰۵/۰۸/۱۰')->toIso8601String())->toBe('2026-10-31T20:30:00+00:00');
    });

    it('round-trips Nowruz', function () {
        $nowruz = JalaliDate::parse('1405/01/01');
        expect(JalaliDate::format($nowruz, 'Y/m/d', 'en'))->toBe('1405/01/01');
    });
});
