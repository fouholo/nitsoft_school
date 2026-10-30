<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Enums;

/**
 * Mois d'une composition du primaire arabe, dans l'ordre de l'année scolaire
 * (septembre à août) — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */
enum SchoolMonth: int
{
    case September = 9;
    case October = 10;
    case November = 11;
    case December = 12;
    case January = 1;
    case February = 2;
    case March = 3;
    case April = 4;
    case May = 5;
    case June = 6;
    case July = 7;
    case August = 8;

    public function label(): string
    {
        return match ($this) {
            self::September => __('Septembre'),
            self::October => __('Octobre'),
            self::November => __('Novembre'),
            self::December => __('Décembre'),
            self::January => __('Janvier'),
            self::February => __('Février'),
            self::March => __('Mars'),
            self::April => __('Avril'),
            self::May => __('Mai'),
            self::June => __('Juin'),
            self::July => __('Juillet'),
            self::August => __('Août'),
        };
    }

    /**
     * Libellé français du relevé, indépendant de la langue de l'interface.
     */
    public function frenchLabel(): string
    {
        return match ($this) {
            self::September => 'Septembre',
            self::October => 'Octobre',
            self::November => 'Novembre',
            self::December => 'Décembre',
            self::January => 'Janvier',
            self::February => 'Février',
            self::March => 'Mars',
            self::April => 'Avril',
            self::May => 'Mai',
            self::June => 'Juin',
            self::July => 'Juillet',
            self::August => 'Août',
        };
    }

    public function arabicLabel(): string
    {
        return match ($this) {
            self::September => 'سبتمبر',
            self::October => 'أكتوبر',
            self::November => 'نوفمبر',
            self::December => 'ديسمبر',
            self::January => 'يناير',
            self::February => 'فبراير',
            self::March => 'مارس',
            self::April => 'أبريل',
            self::May => 'مايو',
            self::June => 'يونيو',
            self::July => 'يوليو',
            self::August => 'أغسطس',
        };
    }

    /**
     * Rang dans l'année scolaire (septembre = 0).
     */
    public function position(): int
    {
        return ($this->value + 3) % 12;
    }
}
