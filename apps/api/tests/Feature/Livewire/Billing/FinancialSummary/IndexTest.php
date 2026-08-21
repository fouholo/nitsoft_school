<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Expense;
use App\Domain\Billing\Models\Payment;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\FinancialSummary\Index;
use Livewire\Livewire;

test('un enseignant n’a pas accès au bilan financier', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    Livewire::test(Index::class)->assertForbidden();
});

test('un directeur voit les groupes par rôle avec les sous-totaux et le total général', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    $caissier = createUserWithRole($establishment, 'caissier');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);

    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 4000, 'paid_at' => '2026-10-01']);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $caissier->id, 'amount' => 1000, 'paid_at' => '2026-10-02']);
    Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $caissier->id, 'amount' => 300, 'spent_at' => '2026-10-03']);

    $component = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id);
    $groups = $component->viewData('groups');
    $caissierGroup = collect($groups)->firstWhere('role', 'caissier');

    expect(array_column($groups, 'role'))->toBe(['directeur', 'caissier'])
        ->and($caissierGroup['collected'])->toBe(1000.0)
        ->and($caissierGroup['spent'])->toBe(300.0)
        ->and($component->viewData('totalCollected'))->toBe(5000.0)
        ->and($component->viewData('totalNet'))->toBe(4700.0);
});

test('un éducateur ne voit que sa propre ligne, pas les totaux des autres', function () {
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');
    $otherEducator = createUserWithRole($establishment, 'educateur');
    actingInEstablishment($establishment);
    test()->actingAs($educator);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);

    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $educator->id, 'amount' => 1000, 'paid_at' => '2026-10-01']);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $otherEducator->id, 'amount' => 9000, 'paid_at' => '2026-10-01']);

    $component = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id);
    $groups = $component->viewData('groups');

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['rows'])->toHaveCount(1)
        ->and($groups[0]['rows'][0]['user_id'])->toBe($educator->id)
        ->and($component->viewData('totalCollected'))->toBe(1000.0)
        ->and($component->viewData('scopedToOwn'))->toBeTrue();
});

test('la plage personnalisée remplace la sélection d’année scolaire', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true, 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30']);

    // Hors année scolaire, mais dans la plage personnalisée ci-dessous.
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 7000, 'paid_at' => '2026-01-15']);

    $component = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id);
    expect($component->viewData('groups'))->toHaveCount(0);

    $component->set('useCustomRange', true)
        ->set('start_date', '2026-01-01')
        ->set('end_date', '2026-01-31');

    expect($component->viewData('totalCollected'))->toBe(7000.0);
});

test('une plage personnalisée invalide affiche un message de validation sans requête', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(Index::class)
        ->set('useCustomRange', true)
        ->set('start_date', '2026-02-10')
        ->set('end_date', '2026-01-01')
        ->assertSee('La date de fin doit être postérieure à la date de début.');
});

test('le message d’état vide s’affiche quand aucun mouvement n’existe sur la période', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertSee('Aucun encaissement ni dépense enregistré sur cette période.');
});

test('le lien PDF reflète la sélection d’année scolaire', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 1000, 'paid_at' => '2026-10-01']);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertSee(route('reports.financial-summary-pdf', ['school_year_id' => $schoolYear->id, 'start_date' => null, 'end_date' => null]));
});
