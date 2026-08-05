<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web (Livewire) : résout l'établissement actif depuis la session de
 * l'utilisateur authentifié. Un utilisateur peut appartenir à plusieurs
 * établissements (table establishment_user) ; l'établissement actif est
 * choisi via un switcher et conservé en session sous "current_establishment_id".
 */
class ResolveTenantFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $establishmentId = $request->session()->get('current_establishment_id');

        if ($establishmentId === null) {
            $establishmentId = $user->accessibleEstablishments()->first()?->id;

            if ($establishmentId !== null) {
                $request->session()->put('current_establishment_id', $establishmentId);
            }
        }

        if ($establishmentId !== null && $user->hasAccessTo((int) $establishmentId)) {
            app()->instance('currentEstablishmentId', (int) $establishmentId);
        }

        return $next($request);
    }
}
