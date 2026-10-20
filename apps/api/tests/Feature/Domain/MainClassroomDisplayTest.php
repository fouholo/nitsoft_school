<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Billing\Models\ArabicLevelFee;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\Track;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\PaymentTracking\Index as PaymentTrackingIndex;
use App\Livewire\GuardianPortal\Dashboard as GuardianDashboard;
use App\Livewire\Students\Index as StudentsIndex;
use App\Models\User;
use Livewire\Livewire;

/*
 * Classe « officielle » d'un élève = sa classe principale, française ou
 * arabe — voir docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    actingInEstablishment($this->establishment);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->arabicClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->student = Student::factory()->create(['establishment_id' => $this->establishment->id, 'last_name' => 'Diallo']);

    $this->arabicEnrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => null,
        'arabic_classroom_id' => $this->arabicClassroom->id,
        'main_track' => Track::Arabe,
        'registration_amount' => 1000,
        'total_paid' => 0,
    ]);
});

test('le suivi des paiements affiche la classe arabe principale d’un élève sans classe française', function () {
    $this->actingAs(createUserWithRole($this->establishment, 'directeur'));

    $rows = Livewire::test(PaymentTrackingIndex::class)
        ->set('school_year_id', $this->schoolYear->id)
        ->assertSee($this->arabicClassroom->name)
        ->viewData('rows');

    expect($rows->firstWhere('student_id', $this->student->id)['classroom']?->is($this->arabicClassroom))->toBeTrue();
});

test('le reçu d’un élève sans classe française se génère', function () {
    $cashier = createUserWithRole($this->establishment, 'caissier');

    $payment = (new PaymentService)->recordPayment($this->arabicEnrollment, [
        'amount' => 500,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
        'reference' => null,
    ], $cashier);

    $this->actingAs($cashier)
        ->get(route('billing.payments.receipt', $payment))
        ->assertOk();
});

test('le portail parents affiche la classe arabe principale de l’enfant', function () {
    $parentUser = User::factory()->create();
    $this->establishment->users()->attach($parentUser->id, ['role' => 'parent', 'is_active' => true]);
    $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
    $guardian->students()->attach($this->student->id, ['establishment_id' => $this->establishment->id, 'status' => GuardianLinkStatus::Approved]);
    $this->actingAs($parentUser);

    Livewire::test(GuardianDashboard::class)->assertSee($this->arabicClassroom->name);
});

test('la liste des élèves affiche les deux classes et filtre ceux sans classe secondaire', function () {
    $this->actingAs(createUserWithRole($this->establishment, 'directeur'));

    $complete = Student::factory()->create(['establishment_id' => $this->establishment->id, 'last_name' => 'Kouyaté']);
    $classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $complete->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $classroom->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
    ]);

    Livewire::test(StudentsIndex::class)
        ->assertSee(__('Classe secondaire'))
        ->assertSee('Diallo')
        ->assertSee('Kouyaté')
        ->assertSee($classroom->name)
        ->set('withoutSecondaryOnly', true)
        ->assertSee('Diallo')
        ->assertDontSee('Kouyaté');
});

test('hors école franco-arabe, la liste des élèves n’a ni colonnes de classes ni filtre', function () {
    $this->establishment->update(['is_arabe' => false]);
    $this->actingAs(createUserWithRole($this->establishment, 'directeur'));

    Livewire::test(StudentsIndex::class)
        ->assertDontSee(__('Sans classe secondaire'))
        ->assertDontSee(__('Classe principale'));
});

test('une remise repart des tranches de la grille arabe quand la classe principale est arabe', function () {
    $installment = Installment::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'position' => 1]);

    $arabicLevelFee = ArabicLevelFee::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicClassroom->arabic_level_id,
    ]);
    $arabicLevelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 40000]);

    $discount = Discount::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'student_id' => $this->student->id,
        'type' => 'fixed',
        'value' => 10000,
    ]);

    app(DiscountService::class)->applyToEnrollment($this->arabicEnrollment, $discount);

    expect((float) $this->arabicEnrollment->refresh()->installment_1_amount)->toBe(30000.0);
});

test('une remise d’un élève à classe principale française reste calculée sur la grille française', function () {
    $installment = Installment::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'position' => 1]);
    $classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'level_id' => $classroom->level_id,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 25000]);

    $this->arabicEnrollment->update(['main_track' => Track::Francais, 'classroom_id' => $classroom->id]);

    $discount = Discount::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'student_id' => $this->student->id,
        'type' => 'fixed',
        'value' => 5000,
    ]);

    app(DiscountService::class)->applyToEnrollment($this->arabicEnrollment->refresh(), $discount);

    expect((float) $this->arabicEnrollment->refresh()->installment_1_amount)->toBe(20000.0);
});

test('les écrans de la filière française ignorent un élève sans classe française', function () {
    $classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);

    expect($classroom->enrollments()->pluck('id'))->not->toContain($this->arabicEnrollment->id)
        ->and(Enrollment::whereNotNull('classroom_id')->pluck('id'))->not->toContain($this->arabicEnrollment->id);
});
