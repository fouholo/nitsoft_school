<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Support;

/**
 * Matrice de permissions par rôle métier — voir
 * docs/superpowers/specs/2026-08-09-privileges-par-role-design.md. Ne couvre
 * pas les vérifications à accès large (students.view, grades.view,
 * guardians.view) qui restent des exclusions minimales dans les Policies
 * concernées, pas des entrées de matrice (cf. plan, point 5 du Contexte).
 */
final class RolePermissions
{
    /**
     * @var array<string, list<string>>
     */
    private const MATRIX = [
        'students.create' => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'students.update' => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'students.delete' => ['fondateur', 'directeur', 'gestionnaire', 'educateur'],

        'staff.update' => ['fondateur', 'directeur', 'gestionnaire', 'educateur'],
        'staff.delete' => ['fondateur', 'directeur'],
        'staff.assign' => ['fondateur', 'directeur', 'educateur'],

        'finance.access' => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'finance.scope_own_only' => ['educateur'],
        'tuition_fees.write' => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'billing.manage' => ['directeur', 'caissier', 'educateur'],
        'payments.delete' => ['fondateur', 'directeur'],
        'expenses.create' => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'expenses.delete' => ['fondateur', 'directeur'],

        'grades.enter' => ['educateur'],
        'terms.write' => ['educateur'],
        'report_cards.generate' => ['educateur'],
        'subject_coefficients.write' => ['educateur'],

        'guardians.notify' => ['caissier'],
    ];

    public static function can(?string $role, string $ability): bool
    {
        if ($role === null) {
            return false;
        }

        return in_array($role, self::MATRIX[$ability] ?? [], true);
    }
}
