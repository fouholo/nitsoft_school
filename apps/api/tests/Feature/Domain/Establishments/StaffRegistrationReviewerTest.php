<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Domain\Establishments\Services\StaffRegistrationReviewer;
use App\Livewire\Establishments\Index;
use App\Models\User;
use Livewire\Livewire;

function pendingStaffPivot(Establishment $establishment, string $role): EstablishmentUserPivot
{
    $user = User::factory()->create();

    EstablishmentUserPivot::create([
        'establishment_id' => $establishment->id,
        'user_id' => $user->id,
        'role' => $role,
        'is_active' => false,
    ]);

    // Pivot::create() ne renseigne pas l'id : on relit la ligne.
    return EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();
}

function pendingFounderPivot(Foundation $foundation): FoundationUserPivot
{
    $user = User::factory()->create();

    FoundationUserPivot::create([
        'foundation_id' => $foundation->id,
        'user_id' => $user->id,
        'role' => 'fondateur',
        'is_active' => false,
    ]);

    return FoundationUserPivot::where('foundation_id', $foundation->id)->where('user_id', $user->id)->sole();
}

beforeEach(function () {
    $this->reviewer = app(StaffRegistrationReviewer::class);
    $this->author = createSaasAdmin('main');
});

test('une inscription sur une école sans administrateur est à examiner par le SaaS', function () {
    $pivot = pendingStaffPivot(Establishment::factory()->create(['foundation_id' => null]), 'directeur');

    expect($this->reviewer->pendingEstablishmentMembers()->pluck('id')->all())->toBe([$pivot->id])
        ->and($this->reviewer->pendingCount())->toBe(1);
});

test('une inscription sur une école qui a déjà un administrateur n’est pas remontée au SaaS', function () {
    $withLocalAdmin = Establishment::factory()->create(['foundation_id' => null]);
    createLocalAdmin($withLocalAdmin);
    pendingStaffPivot($withLocalAdmin, 'gestionnaire');

    $foundation = Foundation::factory()->create();
    $inGroup = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    createGeneralAdmin($foundation);
    pendingStaffPivot($inGroup, 'directeur');
    pendingFounderPivot($foundation);

    expect($this->reviewer->pendingCount())->toBe(0);
});

test('un fondateur en attente sur une fondation sans administrateur général est à examiner', function () {
    $pivot = pendingFounderPivot(Foundation::factory()->create());

    expect($this->reviewer->pendingFounders()->pluck('id')->all())->toBe([$pivot->id]);
});

test('valider un directeur l’active et en fait l’administrateur local', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $pivot = pendingStaffPivot($establishment, 'directeur');

    $this->reviewer->approveEstablishmentMember($pivot, $this->author);

    $pivot->refresh();

    expect($pivot->is_active)->toBeTrue()
        ->and($pivot->is_local_admin)->toBeTrue()
        ->and($pivot->is_general_admin)->toBeNull()
        ->and($pivot->user->roleFor($establishment->id))->toBe('directeur');
});

test('valider un fondateur d’école indépendante en fait l’administrateur général', function () {
    $pivot = pendingStaffPivot(Establishment::factory()->create(['foundation_id' => null]), 'fondateur');

    $this->reviewer->approveEstablishmentMember($pivot, $this->author);

    expect($pivot->refresh()->is_active)->toBeTrue()
        ->and($pivot->is_general_admin)->toBeTrue();
});

test('valider un second directeur ne lui donne pas le pouvoir déjà attribué', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $first = pendingStaffPivot($establishment, 'directeur');
    $second = pendingStaffPivot($establishment, 'gestionnaire');

    $this->reviewer->approveEstablishmentMember($first, $this->author);
    $this->reviewer->approveEstablishmentMember($second, $this->author);

    expect($second->refresh()->is_active)->toBeTrue()
        ->and($second->is_local_admin)->toBeNull();
});

test('valider un fondateur de groupe en fait l’administrateur général de la fondation', function () {
    $pivot = pendingFounderPivot(Foundation::factory()->create());

    $this->reviewer->approveFounder($pivot, $this->author);

    expect($pivot->refresh()->is_active)->toBeTrue()
        ->and($pivot->is_general_admin)->toBeTrue();
});

test('refuser supprime le rattachement et le compte devenu inutile', function () {
    $pivot = pendingStaffPivot(Establishment::factory()->create(['foundation_id' => null]), 'directeur');
    $userId = $pivot->user_id;

    $this->reviewer->reject($pivot, $this->author);

    expect(EstablishmentUserPivot::whereKey($pivot->id)->exists())->toBeFalse()
        ->and(User::whereKey($userId)->exists())->toBeFalse();
});

test('refuser conserve un compte qui sert ailleurs', function () {
    $pivot = pendingStaffPivot(Establishment::factory()->create(['foundation_id' => null]), 'directeur');
    Guardian::create(['user_id' => $pivot->user_id, 'first_name' => 'Awa', 'last_name' => 'Traoré', 'email' => 'awa@example.test']);

    $this->reviewer->reject($pivot, $this->author);

    expect(EstablishmentUserPivot::whereKey($pivot->id)->exists())->toBeFalse()
        ->and(User::whereKey($pivot->user_id)->exists())->toBeTrue();
});

test('l’écran Établissements liste les inscriptions et permet de valider ou refuser', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null, 'name' => 'École Sans Admin']);
    $toApprove = pendingStaffPivot($establishment, 'directeur');
    $toReject = pendingFounderPivot(Foundation::factory()->create(['name' => 'Groupe Sans Admin']));

    actingInEstablishment($establishment);
    $this->actingAs($this->author);

    Livewire::test(Index::class)
        ->assertSee(__('Inscriptions du personnel en attente (:count)', ['count' => 2]))
        ->assertSee($toApprove->user->email)
        ->assertSee('Groupe Sans Admin')
        ->call('approveStaffMember', $toApprove->id)
        ->call('rejectFounder', $toReject->id)
        ->assertHasNoErrors();

    expect($toApprove->refresh()->is_active)->toBeTrue()
        ->and(FoundationUserPivot::whereKey($toReject->id)->exists())->toBeFalse();
});

test('un fondateur d’école ne peut pas valider les inscriptions depuis l’écran global', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $founder = createGeneralAdmin($establishment);
    $other = Establishment::factory()->create(['foundation_id' => null]);
    $pivot = pendingStaffPivot($other, 'directeur');

    expect($founder->can('reviewStaffRegistrations', Establishment::class))->toBeFalse();

    expect($pivot->refresh()->is_active)->toBeFalse();
});
