<?php

use App\Domain\Establishments\Models\Establishment;
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
