<?php

use App\Domain\Billing\Models\Installment;
use App\Domain\Establishments\Enums\SaasAdminType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\SaasAdmin;
use App\Domain\Enrollment\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Crée un utilisateur rattaché à l'établissement donné avec le rôle fourni
 * (voir establishment_user.role — pas de rôles globaux Spatie, cf. plan
 * d'architecture section 2.2).
 */
function createUserWithRole(Establishment $establishment, string $role): User
{
    $user = User::factory()->create();

    $establishment->users()->attach($user->id, ['role' => $role, 'is_active' => true]);

    return $user;
}

/**
 * Lie le tenant courant au container, comme le ferait
 * ResolveTenantFromSession/ResolveTenantFromToken en production.
 */
function actingInEstablishment(Establishment $establishment): void
{
    app()->instance('currentEstablishmentId', $establishment->id);
}

/**
 * Crée un utilisateur fondateur (foundation_user.role = fondateur) — voir
 * Foundation : accès admin-équivalent à tous les établissements du groupe
 * sans ligne establishment_user par école.
 */
function createFounder(Foundation $foundation): User
{
    $user = User::factory()->create();

    $foundation->users()->attach($user->id, ['role' => 'fondateur', 'is_active' => true]);

    return $user;
}

/**
 * Crée un GENERAL_ADMIN pour une fondation ou un établissement indépendant
 * (is_general_admin=true sur le pivot pertinent — voir User::isGeneralAdminOf()).
 */
function createGeneralAdmin(Foundation|Establishment $org, string $role = 'fondateur'): User
{
    $user = User::factory()->create();

    $org->users()->attach($user->id, ['role' => $role, 'is_active' => true, 'is_general_admin' => true]);

    return $user;
}

/**
 * Crée un LOCAL_ADMIN pour un établissement (is_local_admin=true sur
 * establishment_user — voir User::isLocalAdminOf()).
 */
function createLocalAdmin(Establishment $establishment, string $role = 'directeur'): User
{
    $user = User::factory()->create();

    $establishment->users()->attach($user->id, ['role' => $role, 'is_active' => true, 'is_local_admin' => true]);

    return $user;
}

/**
 * Crée un administrateur SaaS (table saas_admins, indépendante des
 * établissements) — remplace l'ancien rôle establishment_user.super_admin.
 */
function createSaasAdmin(string $type = 'main'): User
{
    $user = User::factory()->create();

    SaasAdmin::create([
        'user_id' => $user->id,
        'type' => SaasAdminType::from($type),
        'is_active' => true,
        'is_main' => $type === 'main' ? true : null,
    ]);

    return $user;
}

/**
 * Reconstruit les lignes "reste à payer" d'une inscription — même logique
 * que App\Http\Controllers\Billing\Concerns\BuildsPaymentReminderRows,
 * dupliquée ici pour permettre aux tests de rendre la vue PDF directement
 * sans passer par le contrôleur (voir PaymentReminderPdfTest,
 * PaymentRemindersBatchPdfTest).
 *
 * @return array{rows: list<array{label: string, due_date: ?\Carbon\Carbon, due: float, paid: float, remaining: float}>, total: float}
 */
function paymentReminderRowsFor(Enrollment $enrollment): array
{
    $rows = [];

    $registrationDue = (float) ($enrollment->registration_amount ?? 0);
    $registrationPaid = $enrollment->registrationAmountPaid();

    if ($registrationDue > 0 && $registrationPaid < $registrationDue) {
        $rows[] = [
            'label' => "Frais d'inscription",
            'due_date' => null,
            'due' => $registrationDue,
            'paid' => $registrationPaid,
            'remaining' => $registrationDue - $registrationPaid,
        ];
    }

    $labels = Installment::where('school_year_id', $enrollment->school_year_id)->pluck('label', 'position');

    foreach ($enrollment->tuitionInstallmentsWithStatus() as $installment) {
        if ($installment['status'] === 'paid') {
            continue;
        }

        $rows[] = [
            'label' => $labels->get($installment['position'], "Tranche {$installment['position']}"),
            'due_date' => $installment['due_date'],
            'due' => $installment['amount'],
            'paid' => $installment['paid'],
            'remaining' => $installment['amount'] - $installment['paid'],
        ];
    }

    return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'remaining'))];
}
