<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Expense;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\Expenses\Index;
use Livewire\Livewire;

test('un directeur crée et liste une dépense', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(Index::class)
        ->set('label', 'Fournitures')
        ->set('amount', 5000)
        ->set('spent_at', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $expense = Expense::sole();

    expect($expense->label)->toBe('Fournitures')
        ->and($expense->recorded_by)->toBe($directeur->id);
});

test('un directeur peut supprimer une dépense, pas un caissier', function () {
    // expenses.delete est réservé à fondateur/directeur — le caissier peut
    // saisir des dépenses mais pas les supprimer (RolePermissions).
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    $cashier = createUserWithRole($establishment, 'caissier');
    actingInEstablishment($establishment);

    $expense = Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $cashier->id]);

    test()->actingAs($cashier);
    Livewire::test(Index::class)->call('delete', $expense->id)->assertForbidden();

    test()->actingAs($directeur);
    Livewire::test(Index::class)->call('delete', $expense->id);

    expect(Expense::find($expense->id))->toBeNull();
});

test('un enseignant n’a aucun accès à l’écran des dépenses', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    Livewire::test(Index::class)->assertForbidden();
});

test('un éducateur ne voit que les dépenses qu’il a lui-même saisies', function () {
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');
    $otherEducator = createUserWithRole($establishment, 'educateur');
    actingInEstablishment($establishment);

    $ownExpense = Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $educator->id]);
    Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $otherEducator->id]);

    test()->actingAs($educator);

    $expenses = Livewire::test(Index::class)->viewData('expenses');

    expect($expenses->pluck('id')->all())->toBe([$ownExpense->id]);
});

test('le total affiché correspond à la somme des dépenses visibles', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Expense::factory()->create(['establishment_id' => $establishment->id, 'amount' => 10000, 'recorded_by' => $directeur->id, 'spent_at' => now()]);
    Expense::factory()->create(['establishment_id' => $establishment->id, 'amount' => 15000, 'recorded_by' => $directeur->id, 'spent_at' => now()]);

    Livewire::test(Index::class)
        ->assertSee(money(25000.0));
});

test('le filtre par mois ne montre que les dépenses de ce mois et met à jour le total', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Expense::factory()->create(['establishment_id' => $establishment->id, 'label' => 'Ce mois', 'amount' => 10000, 'recorded_by' => $directeur->id, 'spent_at' => now()]);
    Expense::factory()->create(['establishment_id' => $establishment->id, 'label' => 'Mois dernier', 'amount' => 99000, 'recorded_by' => $directeur->id, 'spent_at' => now()->subMonth()]);

    Livewire::test(Index::class)
        ->assertSee('Ce mois')
        ->assertSee('Mois dernier')
        ->set('month', now()->format('Y-m'))
        ->assertSee('Ce mois')
        ->assertDontSee('Mois dernier')
        ->assertSee(money(10000.0));
});

test('la liste des dépenses est paginée', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Expense::factory()->count(20)->create(['establishment_id' => $establishment->id, 'recorded_by' => $directeur->id]);

    Livewire::test(Index::class)
        ->assertViewHas('expenses', fn ($expenses) => $expenses->count() === 15 && $expenses->total() === 20);
});

test('la confirmation de suppression mentionne le libellé de la dépense', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Expense::factory()->create(['establishment_id' => $establishment->id, 'label' => 'Facture SONABEL', 'recorded_by' => $directeur->id]);

    Livewire::test(Index::class)
        ->assertSee('Supprimer la dépense « Facture SONABEL »');
});
