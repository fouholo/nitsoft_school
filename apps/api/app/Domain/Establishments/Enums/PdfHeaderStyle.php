<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Enums;

/**
 * Modèle d'en-tête des documents PDF d'une école — voir
 * docs/superpowers/specs/2026-10-31-choix-entete-documents-design.md.
 */
enum PdfHeaderStyle: string
{
    case Official = 'official';
    case Logo = 'logo';

    public function label(): string
    {
        return match ($this) {
            self::Official => __('Officiel'),
            self::Logo => __('Logo'),
        };
    }
}
