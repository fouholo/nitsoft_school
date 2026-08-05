<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API / clients offline (Sanctum) : un device est enrôlé pour un seul
 * établissement à la fois. Le tenant est figé dans les abilities du
 * Personal Access Token sous la forme "tenant:{establishment_id}" au
 * moment de l'émission (cf. AuthController::deviceLogin).
 */
class ResolveTenantFromToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        $establishmentId = null;

        if ($token !== null) {
            foreach ($token->abilities as $ability) {
                if (preg_match('/^tenant:(\d+)$/', $ability, $matches) === 1) {
                    $establishmentId = (int) $matches[1];

                    break;
                }
            }
        }

        if ($establishmentId === null) {
            abort(403, "Aucun établissement associé à ce jeton d'accès.");
        }

        app()->instance('currentEstablishmentId', $establishmentId);

        return $next($request);
    }
}
