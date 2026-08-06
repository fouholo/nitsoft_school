<?php

declare(strict_types=1);

namespace App\Domain\Academics\Enums;

enum Cycle: string
{
    case Prescolaire = 'prescolaire';
    case Primaire = 'primaire';
    case Secondaire = 'secondaire';

    public function label(): string
    {
        return match ($this) {
            self::Prescolaire => 'Préscolaire',
            self::Primaire => 'Primaire',
            self::Secondaire => 'Secondaire',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Prescolaire => 'bg-amber-100 text-amber-700',
            self::Primaire => 'bg-sky-100 text-sky-700',
            self::Secondaire => 'bg-slate-100 text-slate-700',
        };
    }
}
