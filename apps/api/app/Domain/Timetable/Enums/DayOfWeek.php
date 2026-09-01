<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Enums;

enum DayOfWeek: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';

    public function label(): string
    {
        return match ($this) {
            self::Monday => __('Lundi'),
            self::Tuesday => __('Mardi'),
            self::Wednesday => __('Mercredi'),
            self::Thursday => __('Jeudi'),
            self::Friday => __('Vendredi'),
        };
    }
}
