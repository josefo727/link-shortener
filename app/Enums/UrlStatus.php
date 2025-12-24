<?php

declare(strict_types=1);

namespace App\Enums;

enum UrlStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Expired => 'Expirado',
        };
    }

    public function isAccessible(): bool
    {
        return $this === self::Active;
    }
}
