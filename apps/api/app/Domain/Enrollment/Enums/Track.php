<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Enums;

/**
 * Filière d'enseignement d'une école franco-arabe. La filière principale
 * d'une inscription (enrollments.main_track) détermine la classe
 * « officielle » de l'élève et la grille de frais appliquée ; l'autre
 * filière est celle de sa classe secondaire — voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
enum Track: string
{
    case Francais = 'francais';
    case Arabe = 'arabe';

    public function label(): string
    {
        return match ($this) {
            self::Francais => __('Française'),
            self::Arabe => __('Arabe'),
        };
    }

    public function other(): self
    {
        return match ($this) {
            self::Francais => self::Arabe,
            self::Arabe => self::Francais,
        };
    }
}
