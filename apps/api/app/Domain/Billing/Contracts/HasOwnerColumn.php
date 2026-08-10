<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

/**
 * Contrat pour les modèles portant une colonne "auteur" (created_by,
 * received_by, recorded_by, ...) utilisée pour restreindre la vue de
 * l'éducateur à ses propres saisies — voir Concerns\HasOwnerScope.
 */
interface HasOwnerColumn
{
    public function ownerColumn(): string;
}
