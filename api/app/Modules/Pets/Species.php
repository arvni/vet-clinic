<?php

declare(strict_types=1);

namespace App\Modules\Pets;

enum Species: string
{
    case Dog = 'dog';
    case Cat = 'cat';
    case Bird = 'bird';
    case Rabbit = 'rabbit';
    case Rodent = 'rodent';
    case Reptile = 'reptile';
    case Other = 'other';

    public function label(?string $locale = null): string
    {
        return __('pets.species.'.$this->value, [], $locale);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
