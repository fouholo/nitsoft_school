<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Students\Show;
use Livewire\Livewire;

test('un directeur voit le bloc situation financière sur la fiche élève', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'status' => 'active',
        'registration_amount' => 500,
    ]);

    Livewire::test(Show::class, ['student' => $student])
        ->assertSee('Situation financière')
        ->assertSee('Retard de 500 F CFA');
});

test('un enseignant ne voit pas le bloc situation financière sur la fiche élève', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(Show::class, ['student' => $student])
        ->assertDontSee('Situation financière');
});
