<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\DiscountPreset;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\DiscountPresets\Index as PresetsIndex;
use App\Livewire\Billing\Discounts\Index as DiscountsIndex;
use App\Livewire\Billing\Enrollments\Show as EnrollmentShow;
use App\Models\User;
use Livewire\Livewire;

/*
 * Catalogue de réductions — voir
 * docs/superpowers/specs/2026-10-31-catalogue-reductions-design.md.
 */

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    actingInEstablishment($this->establishment);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->student = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'level_id' => $this->classroom->level_id,
        'registration_amount' => 20000,
    ]);
    $installment = Installment::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'position' => 1]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 50000]);

    $this->enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'classroom_id' => $this->classroom->id,
        'school_year_id' => $this->schoolYear->id,
        'status' => 'active',
        'registration_amount' => 20000,
        'installment_1_amount' => 50000,
    ]);

    $this->preset = DiscountPreset::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Enfant du personnel',
        'type' => 'percentage',
        'value' => 50,
        'registration_type' => 'fixed_amount',
        'registration_value' => 5000,
    ]);
});

function actAs(string $role): User
{
    $user = createUserWithRole(test()->establishment, $role);
    test()->actingAs($user);

    return $user;
}

function freeDiscount(array $attributes = []): Discount
{
    return Discount::factory()->create([
        'establishment_id' => test()->establishment->id,
        'student_id' => test()->student->id,
        'school_year_id' => test()->schoolYear->id,
        ...$attributes,
    ]);
}

test('seul le fondateur accède au catalogue', function (string $role, int $status) {
    actAs($role);

    $this->get(route('billing.discount-presets.index'))->assertStatus($status);
})->with([
    ['fondateur', 200],
    ['directeur', 403],
    ['gestionnaire', 403],
    ['caissier', 403],
    ['educateur', 403],
]);

test('le fondateur crée, modifie et désactive une entrée', function () {
    $founder = actAs('fondateur');

    Livewire::test(PresetsIndex::class)
        ->call('create')
        ->set('name', 'Fratrie')
        ->set('value', 20)
        ->call('save')
        ->assertHasNoErrors();

    $preset = DiscountPreset::where('name', 'Fratrie')->sole();

    expect($preset->type)->toBe('percentage')
        ->and($preset->registration_type)->toBeNull()
        ->and($preset->establishment_id)->toBe($this->establishment->id)
        ->and($preset->created_by)->toBe($founder->id);

    Livewire::test(PresetsIndex::class)
        ->call('edit', $preset->id)
        ->assertSet('value', 20.0)
        ->assertSet('registration_value', null)
        ->set('value', '')
        ->set('registration_type', 'fixed_amount')
        ->set('registration_value', 1000)
        ->call('save')
        ->assertHasNoErrors()
        ->call('toggleActive', $preset->id);

    $preset->refresh();

    expect($preset->type)->toBeNull()
        ->and($preset->value)->toBeNull()
        ->and($preset->registration_type)->toBe('fixed_amount')
        ->and($preset->is_active)->toBeFalse();
});

test('le catalogue valide le nom et les valeurs', function () {
    actAs('fondateur');

    Livewire::test(PresetsIndex::class)->call('create')
        ->set('name', 'Enfant du personnel')
        ->set('value', 10)
        ->call('save')
        ->assertHasErrors('name');

    Livewire::test(PresetsIndex::class)->call('create')
        ->set('name', 'Vide')
        ->call('save')
        ->assertHasErrors(['value' => 'required_without']);

    Livewire::test(PresetsIndex::class)->call('create')
        ->set('name', 'Trop')
        ->set('registration_value', 101)
        ->call('save')
        ->assertHasErrors('registration_value');

    expect(DiscountPreset::count())->toBe(1);
});

test('le même nom reste possible dans une autre école, et les catalogues sont isolés', function () {
    $other = Establishment::factory()->create();
    $otherPreset = DiscountPreset::factory()->create(['establishment_id' => $other->id, 'name' => 'Enfant du personnel']);

    actAs('caissier');

    expect(DiscountPreset::pluck('id')->all())->toBe([$this->preset->id]);

    Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment])
        ->assertDontSee('value="'.$otherPreset->id.'"', false)
        ->set('discount_preset_id', $otherPreset->id)
        ->call('applyDiscountPreset')
        ->assertHasErrors('discount_preset_id');

    expect(Discount::count())->toBe(0);
});

test('le caissier applique une entrée à la caisse et les montants se recalculent', function () {
    $cashier = actAs('caissier');

    Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment])
        ->assertSee('Enfant du personnel')
        ->set('discount_preset_id', $this->preset->id)
        ->call('applyDiscountPreset')
        ->assertHasNoErrors()
        ->assertSee(money(15000.0));

    $discount = Discount::sole();
    $this->enrollment->refresh();

    expect($discount->discount_preset_id)->toBe($this->preset->id)
        ->and($discount->reason)->toBe('Enfant du personnel')
        ->and($discount->created_by)->toBe($cashier->id)
        ->and((float) $this->enrollment->registration_amount)->toBe(15000.0)
        ->and((float) $this->enrollment->installment_1_amount)->toBe(25000.0);
});

test('le caissier applique une entrée depuis l’écran des réductions, sans voir la saisie libre', function () {
    actAs('caissier');

    Livewire::test(DiscountsIndex::class)
        ->call('create')
        ->assertDontSee(__('— Saisie libre —'))
        ->assertDontSee(__('Motif (optionnel)'))
        ->set('student_id', $this->student->id)
        ->set('discount_preset_id', $this->preset->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Discount::sole()->discount_preset_id)->toBe($this->preset->id)
        ->and((float) $this->enrollment->refresh()->registration_amount)->toBe(15000.0);
});

test('la saisie libre est refusée au caissier, même en envoyant les champs', function (string $role) {
    actAs($role);

    Livewire::test(DiscountsIndex::class)
        ->call('create')
        ->set('student_id', $this->student->id)
        ->set('type', 'fixed_amount')
        ->set('value', 49000)
        ->call('save')
        ->assertHasErrors('discount_preset_id');

    expect(Discount::count())->toBe(0)
        ->and((float) $this->enrollment->refresh()->installment_1_amount)->toBe(50000.0);
})->with(['caissier', 'educateur']);

test('les valeurs envoyées avec une entrée sont ignorées', function () {
    actAs('caissier');

    Livewire::test(DiscountsIndex::class)
        ->call('create')
        ->set('student_id', $this->student->id)
        ->set('discount_preset_id', $this->preset->id)
        ->set('type', 'fixed_amount')
        ->set('value', 49000)
        ->call('save')
        ->assertHasNoErrors();

    expect(Discount::sole()->type)->toBe('percentage')
        ->and((float) Discount::sole()->value)->toBe(50.0);
});

test('le fondateur et le directeur gardent la saisie libre', function (string $role) {
    actAs($role);

    Livewire::test(DiscountsIndex::class)
        ->call('create')
        ->assertSee(__('— Saisie libre —'))
        ->set('student_id', $this->student->id)
        ->set('value', 10)
        ->call('save')
        ->assertHasNoErrors();

    expect(Discount::sole()->discount_preset_id)->toBeNull()
        ->and((float) $this->enrollment->refresh()->installment_1_amount)->toBe(45000.0);
})->with(['fondateur', 'directeur']);

test('le gestionnaire voit la réduction en cours, sans pouvoir la changer', function () {
    actAs('gestionnaire');

    freeDiscount();

    Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment])
        ->assertSee(__('Réduction libre'))
        ->assertDontSee(__('Appliquer'))
        ->set('discount_preset_id', $this->preset->id)
        ->call('applyDiscountPreset')
        ->assertForbidden();
});

test('une réduction libre est en lecture seule pour le caissier', function () {
    actAs('caissier');

    $discount = freeDiscount();

    Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment])
        ->assertSee(__('Cette réduction a été saisie librement : seul le fondateur ou le directeur peut la modifier.'))
        ->assertDontSee(__('Appliquer'))
        ->set('discount_preset_id', $this->preset->id)
        ->call('applyDiscountPreset')
        ->assertForbidden();

    Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment])
        ->call('removeDiscount')
        ->assertForbidden();

    Livewire::test(DiscountsIndex::class)
        ->assertDontSee(__('Modifier'))
        ->call('edit', $discount->id)
        ->assertForbidden();

    Livewire::test(DiscountsIndex::class)
        ->call('delete', $discount->id)
        ->assertForbidden();

    expect(Discount::sole()->discount_preset_id)->toBeNull();
});

test('le caissier remplace et retire une réduction du catalogue, sans cumul', function () {
    actAs('caissier');

    $other = DiscountPreset::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Fratrie',
        'type' => 'percentage',
        'value' => 10,
    ]);

    $component = Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment])
        ->set('discount_preset_id', $this->preset->id)
        ->call('applyDiscountPreset')
        ->set('discount_preset_id', $other->id)
        ->call('applyDiscountPreset')
        ->assertHasNoErrors();

    $this->enrollment->refresh();

    expect(Discount::count())->toBe(1)
        ->and(Discount::sole()->discount_preset_id)->toBe($other->id)
        ->and((float) $this->enrollment->installment_1_amount)->toBe(45000.0)
        ->and((float) $this->enrollment->registration_amount)->toBe(20000.0);

    $component->call('removeDiscount')->assertHasNoErrors();

    $this->enrollment->refresh();

    expect(Discount::count())->toBe(0)
        ->and((float) $this->enrollment->installment_1_amount)->toBe(50000.0);

    $component->set('discount_preset_id', $other->id)->call('applyDiscountPreset')->assertHasNoErrors();

    expect(Discount::sole()->discount_preset_id)->toBe($other->id);
});

test('modifier ou désactiver une entrée ne change pas une réduction déjà accordée', function () {
    actAs('fondateur');

    app(DiscountService::class)->applyPreset($this->student->id, $this->schoolYear->id, $this->preset, auth()->id());

    Livewire::test(PresetsIndex::class)
        ->call('edit', $this->preset->id)
        ->set('value', 90)
        ->call('save')
        ->assertHasNoErrors()
        ->call('toggleActive', $this->preset->id);

    $this->enrollment->refresh();

    expect((float) Discount::sole()->value)->toBe(50.0)
        ->and((float) $this->enrollment->installment_1_amount)->toBe(25000.0);
});

test('une entrée inactive est absente des listes et refusée', function () {
    $this->preset->update(['is_active' => false]);

    actAs('caissier');

    Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment])
        ->assertDontSee('Enfant du personnel')
        ->assertSee(__("Aucune réduction type n'est disponible : le fondateur doit d'abord en créer."))
        ->set('discount_preset_id', $this->preset->id)
        ->call('applyDiscountPreset')
        ->assertHasErrors('discount_preset_id');

    Livewire::test(DiscountsIndex::class)
        ->call('create')
        ->assertDontSee('Enfant du personnel')
        ->set('student_id', $this->student->id)
        ->set('discount_preset_id', $this->preset->id)
        ->call('save')
        ->assertHasErrors('discount_preset_id');

    expect(fn () => app(DiscountService::class)->applyPreset($this->student->id, $this->schoolYear->id, $this->preset, auth()->id()))
        ->toThrow(InvalidArgumentException::class);

    expect(Discount::count())->toBe(0);
});

test('une inscription retirée ne reçoit pas de réduction depuis la caisse', function () {
    actAs('caissier');

    $this->enrollment->update(['status' => 'withdrawn']);

    Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment])
        ->assertDontSee(__('Appliquer'))
        ->set('discount_preset_id', $this->preset->id)
        ->call('applyDiscountPreset')
        ->assertForbidden();
});

test('seuls le fondateur et le directeur modifient les montants à la main', function (string $role, bool $allowed) {
    actAs($role);

    $component = Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment]);

    if ($allowed) {
        $component->assertSee(__('Modifier les montants'))
            ->call('editAmounts')
            ->set('registration_amount', 1000)
            ->call('saveAmounts')
            ->assertHasNoErrors();

        expect((float) $this->enrollment->refresh()->registration_amount)->toBe(1000.0);

        return;
    }

    $component->assertDontSee(__('Modifier les montants'))
        ->call('editAmounts')
        ->assertForbidden();

    Livewire::test(EnrollmentShow::class, ['enrollment' => $this->enrollment])
        ->set('registration_amount', 1000)
        ->call('saveAmounts')
        ->assertForbidden();

    expect((float) $this->enrollment->refresh()->registration_amount)->toBe(20000.0);
})->with([
    ['fondateur', true],
    ['directeur', true],
    ['caissier', false],
    ['educateur', false],
    ['gestionnaire', false],
]);

test('le caissier enregistre toujours un paiement, et pas le fondateur', function () {
    $cashier = createUserWithRole($this->establishment, 'caissier');
    $founder = createUserWithRole($this->establishment, 'fondateur');

    expect($cashier->can('create', Payment::class))->toBeTrue()
        ->and($founder->can('create', Payment::class))->toBeFalse();
});
