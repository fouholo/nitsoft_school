<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Enums;

/**
 * Jours de la semaine des emplois du temps. Le français reste du lundi au
 * vendredi (weekdays()) ; la filière arabe peut aussi avoir cours le samedi
 * et le dimanche, selon les jours choisis par l'école — voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 */
enum DayOfWeek: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    /**
     * @return list<self>
     */
    public static function weekdays(): array
    {
        return [self::Monday, self::Tuesday, self::Wednesday, self::Thursday, self::Friday];
    }

    public function label(): string
    {
        return match ($this) {
            self::Monday => __('Lundi'),
            self::Tuesday => __('Mardi'),
            self::Wednesday => __('Mercredi'),
            self::Thursday => __('Jeudi'),
            self::Friday => __('Vendredi'),
            self::Saturday => __('Samedi'),
            self::Sunday => __('Dimanche'),
        };
    }

    /**
     * Libellé arabe, indépendant de la langue de l'interface (PDF arabes).
     */
    public function arabicLabel(): string
    {
        return match ($this) {
            self::Monday => 'الإثنين',
            self::Tuesday => 'الثلاثاء',
            self::Wednesday => 'الأربعاء',
            self::Thursday => 'الخميس',
            self::Friday => 'الجمعة',
            self::Saturday => 'السبت',
            self::Sunday => 'الأحد',
        };
    }
}
