<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Nationalite;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Students\Index;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createUserWithRole($this->establishment, 'directeur');

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

test('un élève peut être créé sans matricule, plusieurs élèves peuvent en manquer un', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Awa')
        ->set('last_name', 'Traoré')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Yao')
        ->set('last_name', 'Koné')
        ->call('save')
        ->assertHasNoErrors();

    expect(Student::count())->toBe(2)
        ->and(Student::pluck('student_number')->filter()->isEmpty())->toBeTrue();
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

test('les informations d’identité sont enregistrées, avec normalisation de la date d’acte de naissance vide', function () {
    Nationalite::create(['code' => 'CIV', 'libelle' => 'Ivoirienne']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Awa')
        ->set('last_name', 'Traoré')
        ->set('student_number', 'MAT-0003')
        ->set('birth_place', 'Abidjan')
        ->set('nationalite_code', 'CIV')
        ->set('birth_certificate_number', '2024/1234')
        ->set('birth_certificate_date', '')
        ->set('birth_certificate_place', 'Abidjan')
        ->set('residence', 'Cocody')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::sole();

    expect($student->birth_place)->toBe('Abidjan')
        ->and($student->nationalite_code)->toBe('CIV')
        ->and($student->birth_certificate_number)->toBe('2024/1234')
        ->and($student->birth_certificate_date)->toBeNull()
        ->and($student->birth_certificate_place)->toBe('Abidjan')
        ->and($student->residence)->toBe('Cocody');
});

test('un admin peut téléverser une photo pour un élève', function () {
    Storage::fake('public');

    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Awa')
        ->set('last_name', 'Traoré')
        ->set('student_number', 'MAT-0004')
        ->set('photo', UploadedFile::fake()->image('photo.jpg')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::sole();

    expect($student->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($student->photo_path);
});

test('une photo de plus de 100 Ko est rejetée', function () {
    Storage::fake('public');

    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Awa')
        ->set('last_name', 'Traoré')
        ->set('student_number', 'MAT-0005')
        ->set('photo', UploadedFile::fake()->image('photo.jpg')->size(500))
        ->call('save')
        ->assertHasErrors('photo');

    expect(Student::count())->toBe(0);
});

test('remplacer la photo d’un élève supprime l’ancienne du stockage', function () {
    Storage::fake('public');
    Storage::disk('public')->put('students-photos/old.jpg', 'contenu-factice');

    $student = Student::factory()->create([
        'establishment_id' => $this->establishment->id,
        'photo_path' => 'students-photos/old.jpg',
    ]);

    Livewire::test(Index::class)
        ->call('edit', $student->id)
        ->set('photo', UploadedFile::fake()->image('new.jpg')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $student->refresh();

    Storage::disk('public')->assertMissing('students-photos/old.jpg');
    Storage::disk('public')->assertExists($student->photo_path);
});

test('un enseignant ne peut pas créer d’élève', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->call('create')
        ->assertForbidden();
});

test('avancer à l’étape 2 sans les champs obligatoires de l’étape 1 échoue', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->assertSet('currentStep', 1)
        ->call('nextStep')
        ->assertHasErrors(['last_name', 'first_name'])
        ->assertHasNoErrors('student_number')
        ->assertSet('currentStep', 1)
        ->assertSee('Le champ nom est obligatoire');
});

test('le flux en 3 étapes avance jusqu’à l’enregistrement final', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('last_name', 'Traoré')
        ->set('first_name', 'Awa')
        ->set('student_number', 'MAT-0010')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 2)
        ->call('nextStep')
        ->assertSet('currentStep', 3)
        ->call('save')
        ->assertHasNoErrors();

    expect(Student::sole()->last_name)->toBe('Traoré');
});

test('revenir à l’étape précédente conserve les valeurs déjà saisies', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('last_name', 'Traoré')
        ->set('first_name', 'Awa')
        ->set('student_number', 'MAT-0011')
        ->call('nextStep')
        ->call('previousStep')
        ->assertSet('currentStep', 1)
        ->assertSet('last_name', 'Traoré');
});

test('modifier un élève repart toujours de l’étape 1 avec son nom affiché', function () {
    $student = Student::factory()->create([
        'establishment_id' => $this->establishment->id,
        'last_name' => 'Koné',
        'first_name' => 'Yao',
    ]);

    Livewire::test(Index::class)
        ->call('edit', $student->id)
        ->assertSet('currentStep', 1)
        ->assertSee('Modification — Koné Yao');
});

test('cocher « créer et lier un compte » sans e-mail bloque l’enregistrement', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('last_name', 'Traoré')
        ->set('first_name', 'Awa')
        ->set('student_number', 'MAT-0020')
        ->set('father_name', 'Koffi Traoré')
        ->set('createAccountForFather', true)
        ->call('save')
        ->assertHasErrors(['fatherEmail']);

    expect(Student::count())->toBe(0);
});

test('créer un élève avec un compte parent lié crée le tuteur, son compte portail et le lien approuvé', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('last_name', 'Traoré')
        ->set('first_name', 'Awa')
        ->set('student_number', 'MAT-0021')
        ->set('father_name', 'Koffi Traoré')
        ->set('father_phone', '+2250700000001')
        ->set('createAccountForFather', true)
        ->set('fatherEmail', 'koffi.traore@example.test')
        ->set('primaryContact', 'father')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::sole();
    $guardian = Guardian::sole();

    expect($guardian->email)->toBe('koffi.traore@example.test')
        ->and($guardian->first_name)->toBe('Koffi')
        ->and($guardian->last_name)->toBe('Traoré')
        ->and($guardian->user_id)->not->toBeNull();

    $user = User::findOrFail($guardian->user_id);
    expect($user->email)->toBe('koffi.traore@example.test')
        ->and($user->establishments()->wherePivot('role', 'parent')->count())->toBe(1)
        ->and(Hash::check(User::DEFAULT_PASSWORD, $user->password))->toBeTrue();

    $pivot = $student->guardians()->sole()->pivot;
    expect($pivot->relationship->value)->toBe('pere')
        ->and($pivot->status)->toBe(GuardianLinkStatus::Approved)
        ->and($pivot->is_primary_contact)->toBeTrue();
});

test('un seul contact peut être principal parmi père, mère et tuteur', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('last_name', 'Traoré')
        ->set('first_name', 'Awa')
        ->set('student_number', 'MAT-0022')
        ->set('father_name', 'Koffi Traoré')
        ->set('createAccountForFather', true)
        ->set('fatherEmail', 'koffi@example.test')
        ->set('mother_name', 'Aya Kouassi')
        ->set('createAccountForMother', true)
        ->set('motherEmail', 'aya@example.test')
        ->set('primaryContact', 'mother')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::sole();
    $primaries = $student->guardians()->wherePivot('is_primary_contact', true)->get();

    expect($primaries)->toHaveCount(1)
        ->and($primaries->first()->email)->toBe('aya@example.test');
});

test('un tuteur existant identifié par e-mail est réutilisé plutôt que dupliqué', function () {
    $existingUser = User::factory()->create(['email' => 'koffi@example.test']);
    $existingGuardian = Guardian::factory()->create([
        'first_name' => 'Koffi',
        'last_name' => 'Traoré',
        'email' => 'koffi@example.test',
        'user_id' => $existingUser->id,
    ]);

    Livewire::test(Index::class)
        ->call('create')
        ->set('last_name', 'Traoré')
        ->set('first_name', 'Awa')
        ->set('student_number', 'MAT-0023')
        ->set('father_name', 'Koffi Traoré')
        ->set('createAccountForFather', true)
        ->set('fatherEmail', 'koffi@example.test')
        ->call('save')
        ->assertHasNoErrors();

    expect(Guardian::count())->toBe(1)
        ->and(User::where('email', 'koffi@example.test')->count())->toBe(1);

    $student = Student::sole();
    expect($student->guardians()->sole()->id)->toBe($existingGuardian->id);
});
