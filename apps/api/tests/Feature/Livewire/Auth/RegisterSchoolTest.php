<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Inspection;
use App\Domain\Establishments\Models\SchoolRegistration;
use App\Livewire\Auth\RegisterSchool;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function fillSchoolRegistrationAccount(Testable $component, array $overrides = []): Testable
{
    $values = array_merge([
        'first_name' => 'Kouadio',
        'name' => 'Yao',
        'email' => 'kouadio.yao@example.test',
        'pseudo' => 'kyao',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'establishment_name' => 'École Les Palmiers',
    ], $overrides);

    foreach ($values as $property => $value) {
        $component->set($property, $value);
    }

    return $component;
}

test('un fondateur dépose une demande pour une école indépendante', function () {
    $inspection = Inspection::create(['codeiep' => 'IEP-001', 'inspection_name' => 'Inspection 1']);

    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::PrescolairePrimaire->value)
        ->set('inspection_id', (string) $inspection->id)
        ->set('phone', '+225 07 00 00 00 00')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSee(__("Votre demande a été enregistrée. Vous pourrez vous connecter dès qu'elle aura été validée par l'équipe Nitsoft."));

    $registration = SchoolRegistration::sole();

    expect($registration->email)->toBe('kouadio.yao@example.test')
        ->and($registration->establishment_type)->toBe(EstablishmentType::PrescolairePrimaire)
        ->and($registration->inspection_id)->toBe($inspection->id)
        ->and($registration->direction_id)->toBeNull()
        ->and($registration->foundation_name)->toBeNull()
        ->and($registration->address)->toBeNull()
        ->and($registration->password)->not->toBe('password123')
        ->and(Hash::check('password123', $registration->password))->toBeTrue()
        ->and(User::where('email', 'kouadio.yao@example.test')->exists())->toBeFalse();

    $this->assertGuest();
});

test('un fondateur dépose une demande pour un groupe scolaire', function () {
    $direction = Direction::create(['code' => 'DR-ABJ', 'direction_name' => 'Abidjan']);

    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::Secondaire->value)
        ->set('direction_id', (string) $direction->id)
        ->set('has_foundation', true)
        ->set('foundation_name', 'Groupe Les Palmiers')
        ->call('register')
        ->assertHasNoErrors();

    $registration = SchoolRegistration::sole();

    expect($registration->foundation_name)->toBe('Groupe Les Palmiers')
        ->and($registration->direction_id)->toBe($direction->id)
        ->and($registration->inspection_id)->toBeNull();
});

test('l’inspection est obligatoire pour le préscolaire/primaire', function () {
    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::PrescolairePrimaire->value)
        ->call('register')
        ->assertHasErrors(['inspection_id' => 'required']);

    expect(SchoolRegistration::count())->toBe(0);
});

test('la direction est obligatoire pour le secondaire', function () {
    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::Secondaire->value)
        ->call('register')
        ->assertHasErrors(['direction_id' => 'required']);
});

test('le lien non pertinent pour le type est ignoré', function () {
    $inspection = Inspection::create(['codeiep' => 'IEP-001', 'inspection_name' => 'Inspection 1']);
    $direction = Direction::create(['code' => 'DR-ABJ', 'direction_name' => 'Abidjan']);

    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::Secondaire->value)
        ->set('inspection_id', (string) $inspection->id)
        ->set('direction_id', (string) $direction->id)
        ->call('register')
        ->assertHasNoErrors();

    expect(SchoolRegistration::sole()->inspection_id)->toBeNull();
});

test('le nom du groupe est obligatoire quand la case est cochée', function () {
    $inspection = Inspection::create(['codeiep' => 'IEP-001', 'inspection_name' => 'Inspection 1']);

    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::PrescolairePrimaire->value)
        ->set('inspection_id', (string) $inspection->id)
        ->set('has_foundation', true)
        ->call('register')
        ->assertHasErrors(['foundation_name' => 'required']);
});

test('le nom du groupe saisi puis décoché n’est pas conservé', function () {
    $inspection = Inspection::create(['codeiep' => 'IEP-001', 'inspection_name' => 'Inspection 1']);

    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::PrescolairePrimaire->value)
        ->set('inspection_id', (string) $inspection->id)
        ->set('has_foundation', true)
        ->set('foundation_name', 'Groupe abandonné')
        ->set('has_foundation', false)
        ->call('register')
        ->assertHasNoErrors();

    expect(SchoolRegistration::sole()->foundation_name)->toBeNull();
});

test('un e-mail ou un pseudo déjà pris par un compte est refusé', function () {
    User::factory()->create(['email' => 'kouadio.yao@example.test', 'pseudo' => 'kyao']);

    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::Secondaire->value)
        ->call('register')
        ->assertHasErrors(['email' => 'unique', 'pseudo' => 'unique']);
});

test('un e-mail ou un pseudo déjà pris par une demande en attente est refusé', function () {
    $inspection = Inspection::create(['codeiep' => 'IEP-001', 'inspection_name' => 'Inspection 1']);

    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::PrescolairePrimaire->value)
        ->set('inspection_id', (string) $inspection->id)
        ->call('register')
        ->assertHasNoErrors();

    fillSchoolRegistrationAccount(Livewire::test(RegisterSchool::class))
        ->set('establishment_type', EstablishmentType::PrescolairePrimaire->value)
        ->set('inspection_id', (string) $inspection->id)
        ->call('register')
        ->assertHasErrors(['email' => 'unique', 'pseudo' => 'unique']);

    expect(SchoolRegistration::count())->toBe(1);
});
