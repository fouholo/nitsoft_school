<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Enums;

enum Gender: string
{
    case Homme = 'homme';
    case Femme = 'femme';

    public function label(): string
    {
        return match ($this) {
            self::Homme => __('Homme'),
            self::Femme => __('Femme'),
        };
    }
}
