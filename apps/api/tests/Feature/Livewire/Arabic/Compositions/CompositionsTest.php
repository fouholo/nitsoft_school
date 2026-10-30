<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Enums\SchoolMonth;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicComposition;
use App\Domain\Arabic\Models\ArabicCompositionGrade;
use App\Domain\Arabic\Models\ArabicCompositionResult;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicLevelSubject;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Arabic\Services\ArabicCompositionCalculator;
use App\Domain\Enrollment\Enums\Track;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\Compositions\EnterStudent;
use App\Livewire\Arabic\Compositions\Index;
use App\Livewire\Arabic\Compositions\LevelSubjects;
use App\Livewire\Arabic\Compositions\Reports;
use App\Livewire\Arabic\Compositions\Students;
use Livewire\Livewire;

/*
 * Compositions du primaire arabe — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true, 'type' => EstablishmentType::PrescolairePrimaire]);
    actingInEstablishment($this->establishment);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($this->directeur);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->level = ArabicLevel::factory()->create(['cycle' => Cycle::Primaire->value, 'wording_fr' => 'CP1 AR']);
    $this->classroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->level->id,
        'numero' => 'A',
    ]);

    $this->coran = ArabicSubject::factory()->create(['name' => 'القرآن الكريم', 'name_fr' => 'Coran']);
    $this->fiqh = ArabicSubject::factory()->create(['name' => 'الفقه', 'name_fr' => 'Jurisprudence']);
    foreach ([$this->coran, $this->fiqh] as $position => $subject) {
        ArabicLevelSubject::create([
            'establishment_id' => $this->establishment->id,
            'arabic_level_id' => $this->level->id,
            'arabic_subject_id' => $subject->id,
            'sequence' => $position + 1,
        ]);
    }

    $this->composition = ArabicComposition::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'month' => SchoolMonth::June,
        'graded_on' => '2027-06-10',
        'created_by' => $this->directeur->id,
    ]);
});

function arabicPupil(object $test, string $lastName, array $enrollment = []): Enrollment
{
    return Enrollment::factory()->create([
        'establishment_id' => $test->establishment->id,
        'school_year_id' => $test->schoolYear->id,
        'student_id' => Student::factory()->create(['establishment_id' => $test->establishment->id, 'last_name' => $lastName])->id,
        'classroom_id' => null,
        'arabic_classroom_id' => $test->classroom->id,
        'main_track' => Track::Arabe,
        ...$enrollment,
    ]);
}

function arabicMark(object $test, Enrollment $enrollment, ArabicSubject $subject, ?float $score, bool $absent = false): void
{
    ArabicCompositionGrade::create([
        'establishment_id' => $test->establishment->id,
        'arabic_composition_id' => $test->composition->id,
        'enrollment_id' => $enrollment->id,
        'arabic_subject_id' => $subject->id,
        'score' => $score,
        'is_absent' => $absent,
    ]);
}

test('un directeur ajoute, ordonne et retire les matières d’un niveau', function () {
    $newSubject = ArabicSubject::factory()->create(['name' => 'الحساب']);

    Livewire::test(LevelSubjects::class)
        ->assertSet('arabic_level_id', $this->level->id)
        ->call('add', $newSubject->id)
        ->assertHasNoErrors();

    $added = ArabicLevelSubject::where('arabic_subject_id', $newSubject->id)->sole();
    expect($added->sequence)->toBe(3);

    $order = fn () => ArabicLevelSubject::orderBy('sequence')->pluck('arabic_subject_id')->all();

    Livewire::test(LevelSubjects::class)->call('move', $added->id, -1);
    expect($order())->toBe([$this->coran->id, $newSubject->id, $this->fiqh->id]);

    Livewire::test(LevelSubjects::class)->call('remove', $added->id);
    expect($order())->toBe([$this->coran->id, $this->fiqh->id]);
});

test('un enseignant consulte les matières sans pouvoir les modifier', function () {
    $this->actingAs(createUserWithRole($this->establishment, 'enseignant'));

    Livewire::test(LevelSubjects::class)
        ->assertOk()
        ->call('add', ArabicSubject::factory()->create()->id)
        ->assertForbidden();
});

test('seul le directeur crée une composition, une seule par mois', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('month', SchoolMonth::October->value)
        ->set('graded_on', '2026-10-15')
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicComposition::where('month', SchoolMonth::October->value)->count())->toBe(1);

    Livewire::test(Index::class)
        ->call('create')
        ->set('month', SchoolMonth::October->value)
        ->set('graded_on', '2026-10-16')
        ->call('save')
        ->assertHasErrors(['month']);

    $this->actingAs(createUserWithRole($this->establishment, 'gestionnaire'));
    Livewire::test(Index::class)->call('create')->assertForbidden();
});

test('les compositions suivent l’ordre de l’année scolaire', function () {
    ArabicComposition::create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'month' => SchoolMonth::October, 'graded_on' => '2026-10-15', 'created_by' => $this->directeur->id]);

    $months = Livewire::test(Index::class)->viewData('arabicCompositions')->map(fn ($composition) => $composition->month)->all();

    expect($months)->toBe([SchoolMonth::October, SchoolMonth::June]);
});

test('une composition qui a des notes ne peut pas être supprimée', function () {
    $enrollment = arabicPupil($this, 'Bamba');
    arabicMark($this, $enrollment, $this->coran, 8);

    Livewire::test(Index::class)->call('delete', $this->composition->id)->assertHasErrors(['delete']);
    expect(ArabicComposition::count())->toBe(1);

    ArabicCompositionGrade::query()->delete();
    Livewire::test(Index::class)->call('delete', $this->composition->id)->assertHasNoErrors();
    expect(ArabicComposition::count())->toBe(0);
});

test('la saisie enregistre notes, absences et appréciation, avec un aperçu en direct', function () {
    $enrollment = arabicPupil($this, 'Bamba');

    $component = Livewire::test(EnterStudent::class, ['arabicComposition' => $this->composition, 'enrollment' => $enrollment])
        ->assertSee('القرآن الكريم')
        ->set("scores.{$this->coran->id}", '8')
        ->set("scores.{$this->fiqh->id}", '6')
        ->assertViewHas('preview', fn (array $preview) => $preview['total'] === 14.0 && $preview['count'] === 2 && $preview['average'] === 7.0)
        ->set("absences.{$this->fiqh->id}", true)
        ->assertViewHas('preview', fn (array $preview) => $preview['count'] === 1 && $preview['average'] === 8.0)
        ->set('appreciation', 'Bon travail')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('justSaved', true);

    $fiqh = ArabicCompositionGrade::where('arabic_subject_id', $this->fiqh->id)->sole();

    expect((float) ArabicCompositionGrade::where('arabic_subject_id', $this->coran->id)->sole()->score)->toBe(8.0)
        ->and($fiqh->is_absent)->toBeTrue()
        ->and($fiqh->score)->toBeNull()
        ->and(ArabicCompositionResult::sole()->appreciation)->toBe('Bon travail')
        ->and(ArabicCompositionResult::sole()->generated_at)->toBeNull();

    $component->set('absentGenerale', true)->assertSet("absences.{$this->coran->id}", true);
});

test('une note au-dessus de 10 est refusée', function () {
    Livewire::test(EnterStudent::class, ['arabicComposition' => $this->composition, 'enrollment' => arabicPupil($this, 'Bamba')])
        ->set("scores.{$this->coran->id}", '11')
        ->assertHasErrors(["scores.{$this->coran->id}"]);

    expect(ArabicCompositionGrade::count())->toBe(0);
});

test('seul l’enseignant affecté à la classe saisit ses notes', function () {
    $enrollment = arabicPupil($this, 'Bamba');
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(EnterStudent::class, ['arabicComposition' => $this->composition, 'enrollment' => $enrollment])->assertForbidden();
    Livewire::test(Students::class, ['arabicComposition' => $this->composition])->assertDontSee($this->classroom->name);

    ArabicTeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $teacher->id,
        'arabic_classroom_id' => $this->classroom->id,
        'arabic_subject_id' => $this->coran->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(EnterStudent::class, ['arabicComposition' => $this->composition, 'enrollment' => $enrollment])->assertOk();
    Livewire::test(Students::class, ['arabicComposition' => $this->composition])->assertSee($this->classroom->name);
});

test('un élève d’une classe arabe secondaire ou d’une autre année n’a pas de saisie', function () {
    $secondaryClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => ArabicLevel::factory()->create(['cycle' => Cycle::Secondaire->value])->id,
    ]);
    $otherYearClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'arabic_level_id' => $this->level->id]);

    foreach ([$secondaryClassroom, $otherYearClassroom] as $classroom) {
        $enrollment = arabicPupil($this, 'Hors', ['arabic_classroom_id' => $classroom->id]);

        $this->get(route('arabic.compositions.enter', ['arabicComposition' => $this->composition, 'enrollment' => $enrollment]))->assertNotFound();
    }

    Livewire::test(Students::class, ['arabicComposition' => $this->composition])
        ->assertViewHas('classrooms', fn ($classrooms) => $classrooms->pluck('id')->all() === [$this->classroom->id]);
});

test('la génération calcule moyenne, mention et rang, ex æquo compris, et garde l’appréciation', function () {
    $first = arabicPupil($this, 'Premier');
    $tiedA = arabicPupil($this, 'ExaequoA');
    $tiedB = arabicPupil($this, 'ExaequoB');
    $absent = arabicPupil($this, 'Absent');

    arabicMark($this, $first, $this->coran, 10);
    arabicMark($this, $first, $this->fiqh, 8);
    arabicMark($this, $tiedA, $this->coran, 8);
    arabicMark($this, $tiedA, $this->fiqh, 6);
    arabicMark($this, $tiedB, $this->coran, 7);
    arabicMark($this, $tiedB, $this->fiqh, null, true);
    arabicMark($this, $absent, $this->coran, null, true);
    arabicMark($this, $absent, $this->fiqh, null, true);

    ArabicCompositionResult::create([
        'establishment_id' => $this->establishment->id,
        'arabic_composition_id' => $this->composition->id,
        'enrollment_id' => $first->id,
        'arabic_classroom_id' => $this->classroom->id,
        'appreciation' => 'Excellent',
    ]);

    app(ArabicCompositionCalculator::class)->generate($this->composition, $this->classroom);

    $result = fn (Enrollment $enrollment) => ArabicCompositionResult::where('enrollment_id', $enrollment->id)->sole();

    expect((float) $result($first)->average)->toBe(9.0)
        ->and((float) $result($first)->total)->toBe(18.0)
        ->and($result($first)->rank)->toBe(1)
        ->and($result($first)->appreciation)->toBe('Excellent')
        ->and((float) $result($tiedA)->average)->toBe(7.0)
        ->and($result($tiedA)->rank)->toBe(2)
        // Matière absente retirée : 7 ÷ 1 matière notée.
        ->and((float) $result($tiedB)->average)->toBe(7.0)
        ->and($result($tiedB)->rank)->toBe(2)
        ->and($result($absent)->average)->toBeNull()
        ->and($result($absent)->rank)->toBeNull();
});

test('la génération retire le résultat d’un élève qui a quitté la classe', function () {
    $gone = arabicPupil($this, 'Parti');
    arabicMark($this, $gone, $this->coran, 5);

    $calculator = app(ArabicCompositionCalculator::class);
    $calculator->generate($this->composition, $this->classroom);
    expect(ArabicCompositionResult::count())->toBe(1);

    $gone->update(['status' => 'withdrawn']);
    $calculator->generate($this->composition, $this->classroom);

    expect(ArabicCompositionResult::count())->toBe(0);
});

test('l’écran des relevés génère, liste par rang et signale des notes modifiées', function () {
    $pupil = arabicPupil($this, 'Bamba');
    arabicMark($this, $pupil, $this->coran, 9);

    $component = Livewire::test(Reports::class)
        ->set('arabic_composition_id', $this->composition->id)
        ->set('arabic_classroom_id', $this->classroom->id)
        ->assertSee(__('Générer les relevés'))
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSee('Bamba')
        ->assertSee(__('Régénérer les relevés'))
        ->assertViewHas('hasStaleResults', false);

    $this->travel(1)->minutes();
    ArabicCompositionGrade::query()->first()->update(['score' => 2]);

    $component->call('$refresh')->assertViewHas('hasStaleResults', true);
});

test('générer sans matière configurée affiche une erreur claire', function () {
    ArabicLevelSubject::query()->delete();

    Livewire::test(Reports::class)
        ->set('arabic_composition_id', $this->composition->id)
        ->set('arabic_classroom_id', $this->classroom->id)
        ->call('generate')
        ->assertHasErrors(['arabic_classroom_id']);
});

test('un enseignant ne génère pas les relevés', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    ArabicTeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $teacher->id,
        'arabic_classroom_id' => $this->classroom->id,
        'arabic_subject_id' => $this->coran->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $this->actingAs($teacher);

    Livewire::test(Reports::class)
        ->set('arabic_composition_id', $this->composition->id)
        ->set('arabic_classroom_id', $this->classroom->id)
        ->assertDontSee(__('Générer les relevés'))
        ->call('generate')
        ->assertForbidden();
});

test('une école secondaire, une école non arabe, un caissier et un parent n’y ont pas accès', function () {
    $this->establishment->update(['type' => EstablishmentType::Secondaire]);
    Livewire::test(Index::class)->assertForbidden();

    $this->establishment->update(['type' => EstablishmentType::PrescolairePrimaire, 'is_arabe' => false]);
    Livewire::test(Index::class)->assertForbidden();
    Livewire::test(LevelSubjects::class)->assertForbidden();

    $this->establishment->update(['is_arabe' => true]);
    foreach (['caissier', 'parent'] as $role) {
        $this->actingAs(createUserWithRole($this->establishment, $role));
        Livewire::test(Index::class)->assertForbidden();
    }
});

test('le menu d’une école primaire franco-arabe remplace les écrans du secondaire', function () {
    $page = $this->get(route('arabic.compositions.index'))->assertOk();

    $page->assertSee(__('Matières par niveau'))
        ->assertSee(__('Relevés de notes'))
        ->assertDontSee(__('Grilles de notes'));

    $this->establishment->update(['type' => EstablishmentType::Secondaire]);

    $this->get(route('arabic.classrooms.index'))
        ->assertOk()
        ->assertSee(__('Grilles de notes'))
        ->assertSee(__('Matières par niveau'))
        ->assertDontSee(__('Relevés de notes'));
});

test('le relevé PDF s’ouvre pour un élève et pour la classe, et reste introuvable depuis une autre école', function () {
    $pupil = arabicPupil($this, 'Bamba');
    arabicMark($this, $pupil, $this->coran, 9);
    app(ArabicCompositionCalculator::class)->generate($this->composition, $this->classroom);
    $result = ArabicCompositionResult::sole();

    $single = $this->get(route('reports.arabic-composition-result-pdf', $result));
    $single->assertOk();
    expect($single->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($single->headers->get('Content-Disposition'))->toContain('inline');

    $classroomPdf = $this->get(route('reports.arabic-composition-class-pdf', ['arabicComposition' => $this->composition, 'arabicClassroom' => $this->classroom, 'download' => 1]));
    $classroomPdf->assertOk();
    expect($classroomPdf->headers->get('Content-Disposition'))->toContain('attachment');

    $this->actingAs(createUserWithRole($this->establishment, 'enseignant'))
        ->get(route('reports.arabic-composition-result-pdf', $result))
        ->assertForbidden();

    $other = Establishment::factory()->create(['is_arabe' => true, 'type' => EstablishmentType::PrescolairePrimaire]);
    actingInEstablishment($other);
    $this->actingAs(createUserWithRole($other, 'directeur'))
        ->get(route('reports.arabic-composition-result-pdf', $result))
        ->assertNotFound();
});

test('le relevé reprend le livret : colonnes bilingues, absence, résultats, mots arabes dans l’ordre', function () {
    $pupil = arabicPupil($this, 'Bamba');
    arabicMark($this, $pupil, $this->coran, 9);
    arabicMark($this, $pupil, $this->fiqh, null, true);
    $calculator = app(ArabicCompositionCalculator::class);
    $calculator->generate($this->composition, $this->classroom);
    $result = ArabicCompositionResult::sole();

    $html = view('pdf.arabic-composition-report', [
        'arabicComposition' => $this->composition,
        'arabicClassroom' => $this->classroom,
        'establishment' => $this->establishment,
        'headcount' => $calculator->headcount($this->classroom),
        'reports' => [['result' => $result, 'rows' => $calculator->reportRows($result, $this->classroom)]],
        'fontRegularPath' => '',
        'fontBoldPath' => '',
    ])->render();

    expect($html)->toContain('MOIS DE JUIN')
        ->toContain(pdf_rtl('شهر يونيو'))
        ->toContain('Coran')
        ->toContain(pdf_rtl('القرآن الكريم'))
        ->toContain('>Abs<')
        ->toContain('9 / 10')
        ->toContain('1er')
        ->toContain(pdf_rtl('مجموع الدرجات'))
        ->toContain('Visa des parents');
});
