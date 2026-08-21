<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Reports\Index;
use Livewire\Livewire;

test('un enseignant peut accéder à l’écran et voit les classes de l’année en cours', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);

    $currentYear = SchoolYear::factory()->create(['is_current' => true]);
    $otherYear = SchoolYear::factory()->create(['is_current' => false]);
    $classroomThisYear = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $currentYear->id]);
    $classroomOtherYear = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $otherYear->id]);

    $this->actingAs($teacher);
    $component = Livewire::test(Index::class);

    $component->assertSet('school_year_id', $currentYear->id);
    $component->assertViewHas('classrooms', function ($classrooms) use ($classroomThisYear, $classroomOtherYear) {
        return $classrooms->pluck('id')->contains($classroomThisYear->id)
            && ! $classrooms->pluck('id')->contains($classroomOtherYear->id);
    });
});

test('un utilisateur d’un autre établissement ne peut pas accéder à l’écran', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $adminB = createUserWithRole($establishmentB, 'directeur');

    actingInEstablishment($establishmentA);
    $this->actingAs($adminB);

    Livewire::test(Index::class)->assertForbidden();
});

test('la recherche d’élève filtre par nom, prénom ou matricule et le lien de carte n’apparaît qu’après sélection', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    $this->actingAs($admin);

    $awa = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'Traoré', 'first_name' => 'Awa', 'student_number' => 'MAT-0001']);
    $yao = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'Koné', 'first_name' => 'Yao', 'student_number' => 'MAT-0002']);

    $component = Livewire::test(Index::class);

    $component->assertViewHas('cardStudents', fn ($students) => $students->pluck('id')->contains($awa->id) && $students->pluck('id')->contains($yao->id));
    $component->assertDontSee(route('reports.student-id-card-pdf', $awa));

    $component->set('studentSearch', 'MAT-0001')
        ->assertViewHas('cardStudents', fn ($students) => $students->pluck('id')->contains($awa->id) && ! $students->pluck('id')->contains($yao->id));

    $component->set('card_student_id', $awa->id)
        ->assertSee(route('reports.student-id-card-pdf', $awa));
});
