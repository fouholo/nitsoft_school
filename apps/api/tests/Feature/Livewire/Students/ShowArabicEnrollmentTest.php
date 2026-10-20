<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Billing\Models\ArabicLevelFee;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Enrollment\Enums\Track;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Students\Show;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Classe principale / classe secondaire dans une école franco-arabe — voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->directeur);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->arabicClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->student = Student::factory()->create(['establishment_id' => $this->establishment->id]);
});

function enrollmentForm(Student $student): Testable
{
    return Livewire::test(Show::class, ['student' => $student])
        ->call('addEnrollment')
        ->set('enrolled_on', now()->toDateString());
}

test('un élève peut être inscrit avec une classe principale arabe et sans classe française', function () {
    enrollmentForm($this->student)
        ->set('main_track', Track::Arabe->value)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    $enrollment = Enrollment::where('student_id', $this->student->id)->sole();

    expect($enrollment->main_track)->toBe(Track::Arabe)
        ->and($enrollment->arabic_classroom_id)->toBe($this->arabicClassroom->id)
        ->and($enrollment->classroom_id)->toBeNull()
        ->and($enrollment->mainClassroom()?->is($this->arabicClassroom))->toBeTrue();
});

test('une classe principale française peut être accompagnée d’une classe arabe secondaire', function () {
    enrollmentForm($this->student)
        ->set('main_track', Track::Francais->value)
        ->set('classroom_id', $this->classroom->id)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    $enrollment = Enrollment::where('student_id', $this->student->id)->sole();

    expect($enrollment->main_track)->toBe(Track::Francais)
        ->and($enrollment->classroom_id)->toBe($this->classroom->id)
        ->and($enrollment->secondaryClassroom()?->is($this->arabicClassroom))->toBeTrue();
});

test('la classe de la filière principale est obligatoire', function () {
    enrollmentForm($this->student)
        ->set('main_track', Track::Arabe->value)
        ->set('classroom_id', $this->classroom->id)
        ->call('saveEnrollment')
        ->assertHasErrors(['arabic_classroom_id' => 'required']);

    enrollmentForm($this->student)
        ->set('main_track', Track::Francais->value)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->call('saveEnrollment')
        ->assertHasErrors(['classroom_id' => 'required']);

    expect(Enrollment::count())->toBe(0);
});

test('une classe arabe d’un autre établissement ou d’une autre année est refusée', function () {
    $foreign = ArabicClassroom::factory()->create(['school_year_id' => $this->schoolYear->id]);
    $otherYear = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id]);

    foreach ([$foreign, $otherYear] as $arabicClassroom) {
        enrollmentForm($this->student)
            ->set('main_track', Track::Arabe->value)
            ->set('arabic_classroom_id', $arabicClassroom->id)
            ->call('saveEnrollment')
            ->assertHasErrors(['arabic_classroom_id']);
    }

    expect(Enrollment::count())->toBe(0);
});

test('les frais sont préremplis depuis la grille du niveau arabe quand la classe principale est arabe', function () {
    $installment1 = Installment::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'position' => 1]);
    $installment2 = Installment::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'position' => 2]);

    $arabicLevelFee = ArabicLevelFee::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicClassroom->arabic_level_id,
        'registration_amount' => 7000,
    ]);
    $arabicLevelFee->installmentAmounts()->create(['installment_id' => $installment1->id, 'amount' => 9000]);
    $arabicLevelFee->installmentAmounts()->create(['installment_id' => $installment2->id, 'amount' => 6000]);

    // Grille française du niveau de la classe secondaire : ne doit pas servir.
    LevelFee::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'level_id' => $this->classroom->level_id,
        'registration_amount' => 99000,
    ]);

    enrollmentForm($this->student)
        ->set('main_track', Track::Arabe->value)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('classroom_id', $this->classroom->id)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    $enrollment = Enrollment::where('student_id', $this->student->id)->sole();

    expect((float) $enrollment->registration_amount)->toBe(7000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(9000.0)
        ->and((float) $enrollment->installment_2_amount)->toBe(6000.0)
        ->and((float) $enrollment->installment_3_amount)->toBe(0.0);
});

test('les statuts boursier, interne et affecté sont ignorés quand la classe principale est arabe', function () {
    enrollmentForm($this->student)
        ->set('main_track', Track::Arabe->value)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('classroom_id', $this->classroom->id)
        ->set('is_repeating', true)
        ->set('is_arabic_repeating', true)
        ->set('is_scholarship', true)
        ->set('is_boarding', true)
        ->set('is_assigned', true)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    $enrollment = Enrollment::where('student_id', $this->student->id)->sole();

    // La classe française (secondaire) est du secondaire : son redoublement
    // reste possible, indépendamment de celui de la classe arabe.
    expect($enrollment->is_repeating)->toBeTrue()
        ->and($enrollment->is_arabic_repeating)->toBeTrue()
        ->and($enrollment->is_scholarship)->toBeFalse()
        ->and($enrollment->is_boarding)->toBeFalse()
        ->and($enrollment->is_assigned)->toBeFalse();
});

test('le redoublement arabe est ignoré sans classe arabe', function () {
    enrollmentForm($this->student)
        ->set('classroom_id', $this->classroom->id)
        ->set('is_arabic_repeating', true)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    expect(Enrollment::where('student_id', $this->student->id)->sole()->is_arabic_repeating)->toBeFalse();
});

test('hors école franco-arabe, la filière est forcée en français et la classe arabe ignorée', function () {
    $this->establishment->update(['is_arabe' => false]);

    enrollmentForm($this->student)
        ->assertDontSee(__('Filière principale'))
        ->set('main_track', Track::Arabe->value)
        ->set('classroom_id', $this->classroom->id)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('is_arabic_repeating', true)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    $enrollment = Enrollment::where('student_id', $this->student->id)->sole();

    expect($enrollment->main_track)->toBe(Track::Francais)
        ->and($enrollment->arabic_classroom_id)->toBeNull()
        ->and($enrollment->is_arabic_repeating)->toBeFalse();
});

test('la classe secondaire peut être ajoutée, changée puis retirée sans toucher aux frais', function () {
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $this->classroom->id,
        'registration_amount' => 5000,
    ]);

    $component = Livewire::test(Show::class, ['student' => $this->student])
        ->assertSee(__('Classe secondaire à renseigner'))
        ->call('editSecondaryClassroom', $enrollment->id)
        ->set('secondary_classroom_id', $this->arabicClassroom->id)
        ->set('secondary_is_repeating', true)
        ->call('saveSecondaryClassroom')
        ->assertHasNoErrors()
        ->assertDontSee(__('Classe secondaire à renseigner'));

    $enrollment->refresh();
    expect($enrollment->arabic_classroom_id)->toBe($this->arabicClassroom->id)
        ->and($enrollment->is_arabic_repeating)->toBeTrue()
        ->and((float) $enrollment->registration_amount)->toBe(5000.0);

    $otherArabicClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);

    $component->call('editSecondaryClassroom', $enrollment->id)
        ->assertSet('secondary_classroom_id', $this->arabicClassroom->id)
        ->assertSet('secondary_is_repeating', true)
        ->set('secondary_classroom_id', $otherArabicClassroom->id)
        ->call('saveSecondaryClassroom')
        ->assertHasNoErrors();

    expect($enrollment->refresh()->arabic_classroom_id)->toBe($otherArabicClassroom->id);

    $component->call('editSecondaryClassroom', $enrollment->id)
        ->set('secondary_classroom_id', null)
        ->call('saveSecondaryClassroom')
        ->assertHasNoErrors();

    $enrollment->refresh();
    expect($enrollment->arabic_classroom_id)->toBeNull()
        ->and($enrollment->is_arabic_repeating)->toBeFalse();
});

test('la classe secondaire d’un élève à filière principale arabe est une classe française de la même année', function () {
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => null,
        'arabic_classroom_id' => $this->arabicClassroom->id,
        'main_track' => Track::Arabe,
    ]);

    $otherYearClassroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id]);
    $foreignClassroom = Classroom::factory()->create(['school_year_id' => $this->schoolYear->id]);

    $component = Livewire::test(Show::class, ['student' => $this->student])
        ->call('editSecondaryClassroom', $enrollment->id)
        ->set('secondary_classroom_id', $foreignClassroom->id)
        ->call('saveSecondaryClassroom')
        ->assertHasErrors(['secondary_classroom_id']);

    $component->set('secondary_classroom_id', $otherYearClassroom->id)
        ->call('saveSecondaryClassroom')
        ->assertHasErrors(['secondary_classroom_id']);

    $component->set('secondary_classroom_id', $this->classroom->id)
        ->call('saveSecondaryClassroom')
        ->assertHasNoErrors();

    expect($enrollment->refresh()->classroom_id)->toBe($this->classroom->id)
        ->and($enrollment->arabic_classroom_id)->toBe($this->arabicClassroom->id);
});

test('modifier la classe secondaire est refusé hors école franco-arabe', function () {
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->establishment->update(['is_arabe' => false]);

    Livewire::test(Show::class, ['student' => $this->student])
        ->call('editSecondaryClassroom', $enrollment->id)
        ->assertForbidden();
});

test('la fiche affiche la classe arabe principale d’un élève sans classe française', function () {
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => null,
        'arabic_classroom_id' => $this->arabicClassroom->id,
        'main_track' => Track::Arabe,
    ]);

    Livewire::test(Show::class, ['student' => $this->student])
        ->assertOk()
        ->assertSee($this->arabicClassroom->name)
        ->assertSee(__('Classe secondaire à renseigner'));
});

test('une seconde inscription la même année est refusée avec un message clair', function () {
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $this->classroom->id,
        'status' => 'withdrawn',
    ]);

    enrollmentForm($this->student)
        ->set('school_year_id', $this->schoolYear->id)
        ->set('classroom_id', $this->classroom->id)
        ->call('saveEnrollment')
        ->assertHasErrors(['school_year_id' => 'unique'])
        ->assertSee(__('Cet élève a déjà une inscription pour cette année scolaire.'));

    expect(Enrollment::where('student_id', $this->student->id)->count())->toBe(1);
});
