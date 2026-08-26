<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Dashboard\UidSearchWidget;
use Livewire\Livewire;

test('un UID élève valide et existant redirige vers sa fiche', function () {
    $establishment = Establishment::factory()->create();
    $admin = createLocalAdmin($establishment);
    test()->actingAs($admin);
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(UidSearchWidget::class)
        ->set('uid', $student->uid_serveur)
        ->call('search')
        ->assertRedirect(route('students.show', $student));
});

test('un UID élève valide mais inexistant affiche une erreur sans rediriger', function () {
    $establishment = Establishment::factory()->create();
    $admin = createLocalAdmin($establishment);
    test()->actingAs($admin);
    actingInEstablishment($establishment);

    Livewire::test(UidSearchWidget::class)
        ->set('uid', '221999999999')
        ->call('search')
        ->assertNoRedirect()
        ->assertSet('errorMessage', 'Aucun élève trouvé avec ce code.')
        ->assertSet('uid', '');
});

test('un UID d’un élève d’un autre établissement est traité comme introuvable', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $admin = createLocalAdmin($establishmentA);
    test()->actingAs($admin);
    actingInEstablishment($establishmentA);

    $studentB = Student::factory()->create(['establishment_id' => $establishmentB->id]);

    Livewire::test(UidSearchWidget::class)
        ->set('uid', $studentB->uid_serveur)
        ->call('search')
        ->assertNoRedirect()
        ->assertSet('errorMessage', 'Aucun élève trouvé avec ce code.');
});

test('un UID mal formé affiche une erreur générique', function () {
    $establishment = Establishment::factory()->create();
    $admin = createLocalAdmin($establishment);
    test()->actingAs($admin);
    actingInEstablishment($establishment);

    Livewire::test(UidSearchWidget::class)
        ->set('uid', 'abc123')
        ->call('search')
        ->assertNoRedirect()
        ->assertSet('errorMessage', 'Code non reconnu.');
});

test('un préfixe valide mais non pris en charge affiche un message dédié', function () {
    $establishment = Establishment::factory()->create();
    $admin = createLocalAdmin($establishment);
    test()->actingAs($admin);
    actingInEstablishment($establishment);

    Livewire::test(UidSearchWidget::class)
        ->set('uid', '222000000001')
        ->call('search')
        ->assertNoRedirect()
        ->assertSet('errorMessage', "Ce type de code n'est pas encore pris en charge.");
});

test('une soumission avec un champ vide n’a aucun effet', function () {
    $establishment = Establishment::factory()->create();
    $admin = createLocalAdmin($establishment);
    test()->actingAs($admin);
    actingInEstablishment($establishment);

    Livewire::test(UidSearchWidget::class)
        ->set('uid', '')
        ->call('search')
        ->assertNoRedirect()
        ->assertSet('errorMessage', null);
});
