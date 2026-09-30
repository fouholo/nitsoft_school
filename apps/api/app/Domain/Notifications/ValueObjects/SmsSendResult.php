<?php

declare(strict_types=1);

namespace App\Domain\Notifications\ValueObjects;

final class SmsSendResult
{
    /**
     * @param  bool  $retryable  échec passager (réseau, fournisseur indisponible,
     *                           forfait épuisé...) : le SMS reste en attente pour
     *                           être renvoyé, au lieu de passer en échec définitif.
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $retryable = false,
    ) {}
}
