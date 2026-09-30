<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant qu'un utilisateur porte must_change_password (compte créé avec le
 * mot de passe par défaut), toute page le ramène au changement de mot de
 * passe. Seuls restent ouverts cet écran, la déconnexion, le changement de
 * langue et les points d'entrée techniques de Livewire dont l'écran a
 * besoin pour fonctionner.
 */
class EnsurePasswordIsChanged
{
    private const ALLOWED_ROUTES = ['account.password.edit', 'logout', 'locale.switch'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED_ROUTES) || $request->routeIs('*livewire*') || $request->is('livewire*')) {
            return $next($request);
        }

        return redirect()->route('account.password.edit');
    }
}
