<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Exceptions\SchoolRegistrationConflictException;
use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Domain\Establishments\Models\Inspection;
use App\Domain\Establishments\Models\SchoolRegistration;
use App\Domain\Establishments\Services\SchoolRegistrationApprover;
use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

function makeSchoolRegistration(array $overrides = []): SchoolRegistration
{
    return SchoolRegistration::create(array_merge([
        'name' => 'Yao',
        'first_name' => 'Kouadio',
        'email' => 'kouadio.yao@example.test',
        'pseudo' => 'kyao',
        'password' => 'password123',
        'establishment_name' => 'École Les Palmiers',
        'establishment_type' => EstablishmentType::PrescolairePrimaire->value,
        'inspection_id' => Inspection::create(['codeiep' => 'IEP-001', 'inspection_name' => 'Inspection 1'])->id,
        'phone' => '+225 07 00 00 00 00',
        'address' => 'Abidjan',
    ], $overrides));
}

beforeEach(function () {
    $this->approver = app(SchoolRegistrationApprover::class);
    $this->author = createSaasAdmin('main');
});

test('valider une école indépendante crée le compte, l’école et le fondateur administrateur général', function () {
    $registration = makeSchoolRegistration();

    $establishment = $this->approver->approve($registration, $this->author);

    $user = User::where('email', 'kouadio.yao@example.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($establishment->name)->toBe('École Les Palmiers')
        ->and($establishment->slug)->toBe('ecole-les-palmiers')
        ->and($establishment->type)->toBe(EstablishmentType::PrescolairePrimaire)
        ->and($establishment->inspection_id)->toBe($registration->inspection_id)
        ->and($establishment->is_active)->toBeTrue()
        ->and($establishment->foundation_id)->toBeNull()
        ->and($user->first_name)->toBe('Kouadio')
        ->and($user->pseudo)->toBe('kyao')
        ->and($pivot->role)->toBe('fondateur')
        ->and($pivot->is_active)->toBeTrue()
        ->and($pivot->is_general_admin)->toBeTrue()
        ->and($user->accessibleEstablishments()->pluck('id')->all())->toBe([$establishment->id])
        ->and(SchoolRegistration::count())->toBe(0);
});

test('valider un groupe scolaire crée la fondation, l’école rattachée et le fondateur du groupe', function () {
    $direction = Direction::create(['code' => 'DR-ABJ', 'direction_name' => 'Abidjan']);
    $registration = makeSchoolRegistration([
        'establishment_type' => EstablishmentType::Secondaire->value,
        'inspection_id' => null,
        'direction_id' => $direction->id,
        'foundation_name' => 'Groupe Les Palmiers',
    ]);

    $establishment = $this->approver->approve($registration, $this->author);

    $user = User::where('email', 'kouadio.yao@example.test')->sole();
    $foundation = Foundation::sole();
    $pivot = FoundationUserPivot::where('foundation_id', $foundation->id)->where('user_id', $user->id)->sole();

    expect($foundation->name)->toBe('Groupe Les Palmiers')
        ->and($foundation->is_active)->toBeTrue()
        ->and($establishment->foundation_id)->toBe($foundation->id)
        ->and($establishment->direction_id)->toBe($direction->id)
        ->and($pivot->role)->toBe('fondateur')
        ->and($pivot->is_active)->toBeTrue()
        ->and($pivot->is_general_admin)->toBeTrue()
        ->and(EstablishmentUserPivot::where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->accessibleEstablishments()->pluck('id')->all())->toBe([$establishment->id]);
});

test('le fondateur validé se connecte avec son propre mot de passe', function () {
    $this->approver->approve(makeSchoolRegistration(), $this->author);

    Livewire::test(Login::class)
        ->set('identifiant', 'kyao')
        ->set('password', 'password123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs(User::where('email', 'kouadio.yao@example.test')->sole());
});

test('le slug évite un établissement supprimé qui occupe encore le même nom', function () {
    Establishment::factory()->create(['slug' => 'ecole-les-palmiers'])->delete();

    $establishment = $this->approver->approve(makeSchoolRegistration(), $this->author);

    expect($establishment->slug)->toBe('ecole-les-palmiers-1');
});

test('la validation échoue sans rien créer si l’e-mail a été pris entre-temps', function () {
    $registration = makeSchoolRegistration();
    User::factory()->create(['email' => 'kouadio.yao@example.test']);
    $establishmentsBefore = Establishment::count();

    expect(fn () => $this->approver->approve($registration, $this->author))
        ->toThrow(SchoolRegistrationConflictException::class);

    expect(Establishment::count())->toBe($establishmentsBefore)
        ->and(SchoolRegistration::count())->toBe(1);
});

test('la validation échoue si le pseudo a été pris entre-temps, quelle que soit la casse', function () {
    $registration = makeSchoolRegistration();
    User::factory()->create(['pseudo' => 'KYAO']);

    expect(fn () => $this->approver->approve($registration, $this->author))
        ->toThrow(SchoolRegistrationConflictException::class);

    expect(SchoolRegistration::count())->toBe(1);
});

test('refuser supprime la demande sans rien créer', function () {
    $registration = makeSchoolRegistration();
    $usersBefore = User::count();

    $this->approver->reject($registration, $this->author);

    expect(SchoolRegistration::count())->toBe(0)
        ->and(User::count())->toBe($usersBefore);
});
