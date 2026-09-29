<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Exceptions;

use RuntimeException;

/**
 * L'e-mail ou le pseudo d'une demande d'inscription a été pris par un autre
 * compte entre l'envoi de la demande et sa validation.
 */
class SchoolRegistrationConflictException extends RuntimeException
{
    public static function emailTaken(string $email): self
    {
        return new self(__("L'adresse e-mail :email est désormais utilisée par un autre compte.", ['email' => $email]));
    }

    public static function pseudoTaken(string $pseudo): self
    {
        return new self(__('Le pseudo :pseudo est désormais utilisé par un autre compte.', ['pseudo' => $pseudo]));
    }
}
