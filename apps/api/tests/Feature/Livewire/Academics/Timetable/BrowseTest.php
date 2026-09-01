<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\Timetable\Browse;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createLocalAdmin($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
});

test('un enseignant ne peut pas accéder à l’écran de navigation', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Browse::class)->assertForbidden();
});

test('l’onglet Classes ne liste que les classes secondaires de l’année scolaire courante', function () {
    $this->actingAs($this->admin);

    $currentYear = SchoolYear::factory()->create(['is_current' => true]);
    $oldYear = SchoolYear::factory()->create(['is_current' => false]);

    $secondaireCurrent = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $currentYear->id,
    ]);
    $secondaireOldYear = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $oldYear->id,
    ]);
    $primaireCurrent = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $currentYear->id,
    ]);

    Livewire::test(Browse::class)
        ->assertViewHas('classrooms', function ($classrooms) use ($secondaireCurrent, $secondaireOldYear, $primaireCurrent) {
            return $classrooms->pluck('id')->contains($secondaireCurrent->id)
                && ! $classrooms->pluck('id')->contains($secondaireOldYear->id)
                && ! $classrooms->pluck('id')->contains($primaireCurrent->id);
        });
});

test('l’onglet Enseignants liste tous les enseignants actifs, y compris sans séance', function () {
    $this->actingAs($this->admin);

    $teacherWithoutSession = createUserWithRole($this->establishment, 'enseignant');
    $inactiveTeacher = createUserWithRole($this->establishment, 'enseignant');
    $this->establishment->users()->updateExistingPivot($inactiveTeacher->id, ['is_active' => false]);

    Livewire::test(Browse::class)
        ->assertViewHas('teachers', function ($teachers) use ($teacherWithoutSession, $inactiveTeacher) {
            return $teachers->pluck('id')->contains($teacherWithoutSession->id)
                && ! $teachers->pluck('id')->contains($inactiveTeacher->id);
        });
});
