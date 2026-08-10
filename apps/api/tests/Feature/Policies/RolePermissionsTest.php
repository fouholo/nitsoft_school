<?php

declare(strict_types=1);

use App\Domain\Establishments\Support\RolePermissions;

dataset('role_permissions_matrix', [
    // ability => [role => attendu]
    'students.create' => ['students.create', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => true, 'caissier' => true, 'educateur' => true]],
    'students.delete' => ['students.delete', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => true, 'caissier' => false, 'educateur' => true]],
    'staff.update' => ['staff.update', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => true, 'caissier' => false, 'educateur' => true]],
    'staff.delete' => ['staff.delete', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => false, 'caissier' => false, 'educateur' => false]],
    'staff.assign' => ['staff.assign', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => false, 'caissier' => false, 'educateur' => true]],
    'finance.access' => ['finance.access', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => true, 'caissier' => true, 'educateur' => true]],
    'finance.scope_own_only' => ['finance.scope_own_only', ['fondateur' => false, 'directeur' => false, 'gestionnaire' => false, 'caissier' => false, 'educateur' => true]],
    'tuition_fees.write' => ['tuition_fees.write', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => true, 'caissier' => true, 'educateur' => true]],
    'billing.manage' => ['billing.manage', ['fondateur' => false, 'directeur' => true, 'gestionnaire' => false, 'caissier' => true, 'educateur' => true]],
    'payments.delete' => ['payments.delete', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => false, 'caissier' => false, 'educateur' => false]],
    'expenses.create' => ['expenses.create', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => true, 'caissier' => true, 'educateur' => true]],
    'expenses.delete' => ['expenses.delete', ['fondateur' => true, 'directeur' => true, 'gestionnaire' => false, 'caissier' => false, 'educateur' => false]],
    'grades.enter' => ['grades.enter', ['fondateur' => false, 'directeur' => false, 'gestionnaire' => false, 'caissier' => false, 'educateur' => true]],
    'guardians.notify' => ['guardians.notify', ['fondateur' => false, 'directeur' => false, 'gestionnaire' => false, 'caissier' => true, 'educateur' => false]],
]);

test('la matrice RolePermissions correspond à la spec pour chaque ability et chaque rôle', function (string $ability, array $expectedByRole) {
    foreach ($expectedByRole as $role => $expected) {
        expect(RolePermissions::can($role, $ability))
            ->toBe($expected, "RolePermissions::can('{$role}', '{$ability}') devrait être ".($expected ? 'true' : 'false'));
    }
})->with('role_permissions_matrix');

test('un rôle ou une ability inconnue ne fait jamais planter la matrice', function () {
    expect(RolePermissions::can('enseignant', 'students.create'))->toBeFalse()
        ->and(RolePermissions::can(null, 'students.create'))->toBeFalse()
        ->and(RolePermissions::can('directeur', 'ability.inexistante'))->toBeFalse();
});
