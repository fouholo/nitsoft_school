<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Services\ArabicClassroomRoster;
use App\Domain\Enrollment\Enums\Track;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Livewire\Arabic\Classrooms\Index as ArabicClassroomsIndex;
use App\Livewire\Reports\Index as ReportsIndex;
use Livewire\Livewire;

/*
 * Liste PDF d'une classe arabe — voir
 * docs/superpowers/specs/2026-10-20-liste-pdf-classe-arabe-design.md.
 */

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    actingInEstablishment($this->establishment);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->arabicLevel = ArabicLevel::factory()->create(['wording' => 'الصف الأول', 'wording_fr' => '1ère année', 'requires_series' => true]);
    $this->arabicSerie = ArabicSerie::factory()->create(['serie' => 'L', 'serie_wording' => 'أدبي']);
    $this->arabicClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'arabic_serie_id' => $this->arabicSerie->id,
        'numero' => '2',
    ]);
});

function enrollInArabicClassroom(ArabicClassroom $arabicClassroom, array $student, array $enrollment = []): Student
{
    $created = Student::factory()->create(['establishment_id' => $arabicClassroom->establishment_id, ...$student]);

    Enrollment::factory()->create([
        'establishment_id' => $arabicClassroom->establishment_id,
        'school_year_id' => $arabicClassroom->school_year_id,
        'student_id' => $created->id,
        'arabic_classroom_id' => $arabicClassroom->id,
        ...$enrollment,
    ]);

    return $created;
}

function renderArabicClassroomList(ArabicClassroom $arabicClassroom, ?string $gender = null, ?string $repeating = null): string
{
    $roster = app(ArabicClassroomRoster::class);

    return view('pdf.arabic-classroom-student-list', [
        'arabicClassroom' => $arabicClassroom->loadMissing(['arabicLevel', 'arabicSerie', 'schoolYear', 'establishment.inspection.direction']),
        'students' => $roster->students($arabicClassroom, $gender, $repeating),
        'filterLabels' => $roster->filterLabels($gender, $repeating),
        'generalInformation' => GeneralInformation::current(),
        'fontRegularPath' => '',
        'fontBoldPath' => '',
    ])->render();
}

test('la liste réunit les élèves de la classe arabe, principale ou secondaire, triés par nom', function () {
    enrollInArabicClassroom($this->arabicClassroom, ['last_name' => 'Zerbo', 'first_name' => 'Awa']);
    enrollInArabicClassroom($this->arabicClassroom, ['last_name' => 'Bamba', 'first_name' => 'Issa'], [
        'classroom_id' => null,
        'main_track' => Track::Arabe,
    ]);
    enrollInArabicClassroom($this->arabicClassroom, ['last_name' => 'Bamba', 'first_name' => 'Ali']);
    enrollInArabicClassroom($this->arabicClassroom, ['last_name' => 'Retiré'], ['status' => 'withdrawn']);
    enrollInArabicClassroom(
        ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]),
        ['last_name' => 'Ailleurs'],
    );

    $names = app(ArabicClassroomRoster::class)->students($this->arabicClassroom)
        ->map(fn (Student $student) => "{$student->last_name} {$student->first_name}")
        ->all();

    expect($names)->toBe(['Bamba Ali', 'Bamba Issa', 'Zerbo Awa']);
});

test('les filtres sexe et redoublement arabe s’appliquent seuls puis ensemble', function () {
    enrollInArabicClassroom($this->arabicClassroom, ['last_name' => 'Garcon', 'gender' => 'm']);
    enrollInArabicClassroom($this->arabicClassroom, ['last_name' => 'GarconRedoublant', 'gender' => 'm'], ['is_arabic_repeating' => true]);
    enrollInArabicClassroom($this->arabicClassroom, ['last_name' => 'Fille', 'gender' => 'f'], ['is_repeating' => true]);

    $roster = app(ArabicClassroomRoster::class);
    $names = fn (?string $gender, ?string $repeating) => $roster->students($this->arabicClassroom, $gender, $repeating)->pluck('last_name')->all();

    expect($names('f', null))->toBe(['Fille'])
        ->and($names(null, '1'))->toBe(['GarconRedoublant'])
        // Le redoublement français (is_repeating) n'entre pas en compte.
        ->and($names(null, '0'))->toBe(['Fille', 'Garcon'])
        ->and($names('m', '0'))->toBe(['Garcon'])
        ->and($names('x', 'oui'))->toHaveCount(3);
});

test('le document est en arabe sous l’en-tête officiel, avec le nom de la classe et les filtres', function () {
    enrollInArabicClassroom($this->arabicClassroom, ['last_name' => 'Koné', 'first_name' => 'Mariam', 'gender' => 'f', 'student_number' => 'MAT-77']);

    $html = renderArabicClassroomList($this->arabicClassroom, 'f', '0');

    // Mots arabes en ordre inversé : voir pdf_rtl() (dompdf les pose de gauche à droite).
    expect($html)->toContain(pdf_rtl('قائمة تلاميذ القسم — الصف الأول أدبي 2'))
        ->toContain($this->arabicClassroom->name)
        ->toContain(pdf_rtl('إناث · غير معيدين'))
        ->toContain('اللقب')
        ->toContain('Koné')
        ->toContain('MAT-77')
        ->toContain('>أ<')
        ->toContain('المدير')
        ->toContain('في أنشئت')
        ->toContain('REPUBLIQUE DE CÔTE D\'IVOIRE')
        ->not->toContain('Le directeur');
});

test('une classe arabe sans élève affiche la ligne vide', function () {
    expect(renderArabicClassroomList($this->arabicClassroom))->toContain('القسم هذا في مسجلون تلاميذ يوجد لا');
});

test('le PDF s’ouvre dans le navigateur ou se télécharge', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');

    $inline = $this->actingAs($teacher)->get(route('reports.arabic-classroom-students-pdf', $this->arabicClassroom));
    $inline->assertOk();
    expect($inline->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($inline->headers->get('Content-Disposition'))->toContain('inline');

    $download = $this->actingAs($teacher)->get(route('reports.arabic-classroom-students-pdf', ['arabicClassroom' => $this->arabicClassroom, 'download' => 1, 'gender' => 'm']));
    $download->assertOk();
    expect($download->headers->get('Content-Disposition'))->toContain('attachment')
        ->toContain('liste-eleves-arabe-');
});

test('la classe arabe d’un autre établissement est introuvable', function () {
    $other = Establishment::factory()->create(['is_arabe' => true]);
    $directeur = createUserWithRole($other, 'directeur');
    actingInEstablishment($other);

    $this->actingAs($directeur)
        ->get(route('reports.arabic-classroom-students-pdf', $this->arabicClassroom))
        ->assertNotFound();
});

test('l’écran Listes/Rapports propose la liste d’une classe arabe avec ses filtres', function () {
    $this->actingAs(createUserWithRole($this->establishment, 'directeur'));

    Livewire::test(ReportsIndex::class)
        ->assertSee(__("Liste des élèves d'une classe arabe"))
        ->assertSet('arabic_school_year_id', $this->schoolYear->id)
        ->assertSee($this->arabicClassroom->name)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabicGenderFilter', 'f')
        ->set('arabicRepeatingFilter', '1')
        ->assertSee(route('reports.arabic-classroom-students-pdf', ['arabicClassroom' => $this->arabicClassroom->id, 'gender' => 'f', 'repeating' => '1']))
        ->set('arabic_school_year_id', SchoolYear::factory()->create()->id)
        ->assertSet('arabic_classroom_id', null);
});

test('hors école franco-arabe, le bloc de la liste arabe n’apparaît pas', function () {
    $this->establishment->update(['is_arabe' => false]);
    $this->actingAs(createUserWithRole($this->establishment, 'directeur'));

    Livewire::test(ReportsIndex::class)->assertDontSee(__("Liste des élèves d'une classe arabe"));
});

test('l’écran des classes arabes propose la liste PDF de chaque classe', function () {
    $this->actingAs(createUserWithRole($this->establishment, 'directeur'));

    Livewire::test(ArabicClassroomsIndex::class)
        ->assertSee(__('Liste PDF'))
        ->assertSee(route('reports.arabic-classroom-students-pdf', $this->arabicClassroom), false);
});
