<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Enums;

enum GuardianRelationship: string
{
    case Pere = 'pere';
    case Mere = 'mere';
    case Tuteur = 'tuteur';

    public function label(): string
    {
        return match ($this) {
            self::Pere => 'Père',
            self::Mere => 'Mère',
            self::Tuteur => 'Tuteur',
        };
    }
}
