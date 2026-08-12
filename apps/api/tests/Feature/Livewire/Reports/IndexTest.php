<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Reports\Index;
use Livewire\Livewire;

test('un enseignant peut accéder à l’écran et voit les classes de l’année en cours', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);

    $currentYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $otherYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => false]);
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
