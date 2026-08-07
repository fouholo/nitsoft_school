<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Students\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createUserWithRole($this->establishment, 'admin');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);
});

test('un admin peut créer un élève sans date de naissance renseignée', function () {
    // Régression : un champ date vide arrive comme '' (pas null) depuis le
    // formulaire, ce qui faisait planter l'insertion SQL (colonne DATE).
    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Awa')
        ->set('last_name', 'Traoré')
        ->set('student_number', 'MAT-0001')
        ->set('birth_date', '')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::sole();

    expect($student->first_name)->toBe('Awa')
        ->and($student->birth_date)->toBeNull()
        ->and($student->establishment_id)->toBe($this->establishment->id);
});

test('le matricule doit être unique au sein d’un même établissement', function () {
    Student::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_number' => 'MAT-0001',
    ]);

    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Awa')
        ->set('last_name', 'Traoré')
        ->set('student_number', 'MAT-0001')
        ->call('save')
        ->assertHasErrors('student_number');
});

test('les contacts familiaux de référence sont enregistrés et normalisés', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Awa')
        ->set('last_name', 'Traoré')
        ->set('student_number', 'MAT-0002')
        ->set('father_name', 'Koffi Traoré')
        ->set('father_phone', '+2250700000001')
        ->set('mother_name', '')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::sole();

    expect($student->father_name)->toBe('Koffi Traoré')
        ->and($student->father_phone)->toBe('+2250700000001')
        ->and($student->mother_name)->toBeNull()
        ->and($student->mother_phone)->toBeNull()
        ->and($student->tutor_name)->toBeNull();
});

test('un enseignant ne peut pas créer d’élève', function () {
    $teacher = createUserWithRole($this->establishment, 'teacher');
    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->call('create')
        ->assertForbidden();
});
