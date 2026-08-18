<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Students\Show;
use Livewire\Livewire;

test('les statuts secondaire n’apparaissent pas quand la classe sélectionnée est primaire', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(Show::class, ['student' => $student])
        ->call('addEnrollment')
        ->set('classroom_id', $classroom->id)
        ->assertDontSee('Redoublant')
        ->assertDontSee('Boursier')
        ->assertDontSee('Internat')
        ->assertDontSee('Affecté(e)');
});

test('les statuts secondaire apparaissent quand la classe sélectionnée est du secondaire', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(Show::class, ['student' => $student])
        ->call('addEnrollment')
        ->set('classroom_id', $classroom->id)
        ->assertSee('Redoublant')
        ->assertSee('Boursier')
        ->assertSee('Internat')
        ->assertSee('Affecté(e)');
});

test('les statuts saisis pour une inscription secondaire sont persistés', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(Show::class, ['student' => $student])
        ->call('addEnrollment')
        ->set('classroom_id', $classroom->id)
        ->set('school_year_id', $schoolYear->id)
        ->set('enrolled_on', now()->toDateString())
        ->set('is_repeating', true)
        ->set('is_scholarship', true)
        ->set('is_boarding', false)
        ->set('is_assigned', true)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    $enrollment = Enrollment::where('student_id', $student->id)->sole();

    expect($enrollment->is_repeating)->toBeTrue()
        ->and($enrollment->is_scholarship)->toBeTrue()
        ->and($enrollment->is_boarding)->toBeFalse()
        ->and($enrollment->is_assigned)->toBeTrue();
});

test('les statuts transmis pour une classe primaire sont ignorés côté serveur', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(Show::class, ['student' => $student])
        ->call('addEnrollment')
        ->set('classroom_id', $classroom->id)
        ->set('school_year_id', $schoolYear->id)
        ->set('enrolled_on', now()->toDateString())
        // Ces propriétés ne sont normalement pas modifiables depuis la vue pour
        // le primaire, mais on vérifie que le serveur ne leur fait pas confiance.
        ->set('is_repeating', true)
        ->set('is_assigned', true)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    $enrollment = Enrollment::where('student_id', $student->id)->sole();

    expect($enrollment->is_repeating)->toBeFalse()
        ->and($enrollment->is_assigned)->toBeFalse();
});

test('le tableau des inscriptions affiche des badges pour les statuts actifs', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
        'is_repeating' => true,
        'is_boarding' => true,
    ]);

    Livewire::test(Show::class, ['student' => $student])
        ->assertSee('Redoublant')
        ->assertSee('Internat')
        ->assertDontSee('Boursier')
        ->assertDontSee('Affecté(e)');
});
