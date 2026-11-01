<?php

declare(strict_types=1);

namespace App\Modules\Identity;

enum Role: string
{
    case Owner = 'owner';
    case Vet = 'vet';
    case Vaccinator = 'vaccinator';
    case Dispatcher = 'dispatcher';
    case Admin = 'admin';

    /** @return list<string> */
    public static function staffRoles(): array
    {
        return [self::Vet->value, self::Vaccinator->value, self::Dispatcher->value, self::Admin->value];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
