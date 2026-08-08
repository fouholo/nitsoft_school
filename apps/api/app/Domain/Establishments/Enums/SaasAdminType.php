<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Enums;

enum SaasAdminType: string
{
    case Main = 'main';
    case Second = 'second';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Principal',
            self::Second => 'Secondaire',
        };
    }
}
