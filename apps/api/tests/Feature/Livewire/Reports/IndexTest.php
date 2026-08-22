<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
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

test('le bloc lettres de relance n’apparaît que pour un utilisateur ayant accès à la facturation', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);

    $this->actingAs($directeur);
    Livewire::test(Index::class)->assertSee('Lettres de relance');

    $this->actingAs($teacher);
    Livewire::test(Index::class)->assertDontSee('Lettres de relance');
});

test('le bouton de rappel d’échéance est présent à côté du bouton de relance', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    $this->actingAs($directeur);

    Livewire::test(Index::class)
        ->assertSee('Générer les lettres de relance')
        ->assertSee("Générer les rappels d'échéance");
});

test('les filtres statut et bourse n’apparaissent que pour un établissement secondaire', function () {
    $secondaire = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $primaire = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $adminSecondaire = createUserWithRole($secondaire, 'directeur');
    $adminPrimaire = createUserWithRole($primaire, 'directeur');

    actingInEstablishment($secondaire);
    $this->actingAs($adminSecondaire);
    Livewire::test(Index::class)
        ->assertSee('Statut')
        ->assertSee('Bourse')
        ->assertSee('Sexe')
        ->assertSee('Redoublement');

    actingInEstablishment($primaire);
    $this->actingAs($adminPrimaire);
    Livewire::test(Index::class)
        ->assertDontSee('Statut')
        ->assertDontSee('Bourse')
        ->assertSee('Sexe')
        ->assertSee('Redoublement');
});

test('le lien de la liste des élèves reflète les filtres sélectionnés', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    $this->actingAs($admin);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->set('genderFilter', 'f')
        ->set('repeatingFilter', '1')
        ->assertSee(route('reports.classroom-students-pdf', [
            'classroom' => $classroom->id,
            'gender' => 'f',
            'assigned' => '',
            'repeating' => '1',
            'scholarship' => '',
        ]));
});
