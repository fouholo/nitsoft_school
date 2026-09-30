<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Limitation de tentatives pour les actions Livewire exposées au public
 * (connexion, inscriptions) ou sensibles (recherche d'élève par UID) : le
 * point d'entrée /livewire/update n'a aucun throttle par défaut.
 */
trait ThrottlesSubmissions
{
    /**
     * Compte une tentative et refuse au-delà du plafond.
     *
     * @param  string  $field  champ sur lequel afficher le message d'erreur
     * @param  string|null  $discriminator  ce qui distingue l'appelant (IP par défaut)
     *
     * @throws ValidationException
     */
    protected function throttle(string $action, int $maxAttempts, int $decaySeconds, string $field, ?string $discriminator = null): void
    {
        $key = $this->throttleKey($action, $discriminator);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                $field => __('Trop de tentatives. Veuillez réessayer dans :seconds secondes.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    protected function clearThrottle(string $action, ?string $discriminator = null): void
    {
        RateLimiter::clear($this->throttleKey($action, $discriminator));
    }

    private function throttleKey(string $action, ?string $discriminator): string
    {
        return $action.'|'.($discriminator ?? (string) request()->ip());
    }
}
