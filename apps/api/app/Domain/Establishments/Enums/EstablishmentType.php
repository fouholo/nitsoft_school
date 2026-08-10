<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Enums;

enum EstablishmentType: string
{
    case PrescolairePrimaire = 'prescolaire_primaire';
    case Secondaire = 'secondaire';

    public function label(): string
    {
        return match ($this) {
            self::PrescolairePrimaire => 'Préscolaire/Primaire',
            self::Secondaire => 'Secondaire',
        };
    }
}
