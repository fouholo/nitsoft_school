<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicLevelSubject;
use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Arabic\Services\ArabicReportCardService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\Compositions\Index as CompositionsIndex;
use App\Livewire\Arabic\Compositions\LevelSubjects;
use App\Livewire\Arabic\Terms\Index as TermsIndex;
use App\Livewire\Establishments\Index as EstablishmentsIndex;
use App\Livewire\Students\Index as StudentsIndex;
use Livewire\Livewire;

/*
 * Bulletin du secondaire arabe — voir
 * docs/superpowers/specs/2026-10-30-bulletin-secondaire-arabe-design.md.
 */

beforeEach(function () {
    $this->establishment = Establishment::factory()->create([
        'name' => 'ECOLE AL FALAH',
        'name_ar' => 'مدرسة الفلاح الخاصة',
        'city' => 'Abidjan',
        'is_arabe' => true,
        'type' => EstablishmentType::Secondaire,
    ]);
    actingInEstablishment($this->establishment);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($this->directeur);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->level = ArabicLevel::factory()->create(['cycle' => Cycle::Secondaire->value, 'wording' => 'الثالث المتوسط', 'wording_fr' => '3e']);
    $this->classroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->level->id,
    ]);
    $this->term = ArabicTerm::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'label' => '3e Trimestre',
        'label_ar' => 'الفترة الأخيرة',
    ]);
    $this->hadith = ArabicSubject::factory()->create(['name' => 'الحديث الشريف', 'name_fr' => 'Hadith']);
    $this->fiqh = ArabicSubject::factory()->create(['name' => 'الفقه', 'name_fr' => 'Jurisprudence']);
});

function bulletinPupil(object $test, array $student = []): Enrollment
{
    return Enrollment::factory()->create([
        'establishment_id' => $test->establishment->id,
        'school_year_id' => $test->schoolYear->id,
        'student_id' => Student::factory()->create(['establishment_id' => $test->establishment->id, 'last_name' => 'Sylla', 'first_name' => 'Yacoub', ...$student])->id,
        'arabic_classroom_id' => $test->classroom->id,
    ]);
}

function bulletinMark(object $test, Enrollment $enrollment, ArabicSubject $subject, float $score): void
{
    $sheet = ArabicGradeSheet::factory()->create([
        'establishment_id' => $test->establishment->id,
        'arabic_classroom_id' => $test->classroom->id,
        'arabic_subject_id' => $subject->id,
        'arabic_term_id' => $test->term->id,
    ]);

    ArabicGrade::factory()->create([
        'establishment_id' => $test->establishment->id,
        'arabic_grade_sheet_id' => $sheet->id,
        'enrollment_id' => $enrollment->id,
        'score' => $score,
    ]);
}

function renderBulletin(ArabicReportCard $reportCard): string
{
    $service = app(ArabicReportCardService::class);
    $reportCard->loadMissing(['enrollment.student', 'enrollment.arabicClassroom.arabicLevel', 'arabicTerm.schoolYear', 'establishment']);

    return view('pdf.arabic-report-card', [
        'reportCard' => $reportCard,
        'breakdown' => $service->subjectBreakdown($reportCard),
        'headcount' => $service->headcount($reportCard),
        'fontRegularPath' => '',
        'fontBoldPath' => '',
    ])->render();
}

test('le bulletin reprend le modèle : en-tête bilingue, identité, résultats, signatures', function () {
    $enrollment = bulletinPupil($this, ['arabic_name' => 'سيلا يعقوب']);
    bulletinMark($this, $enrollment, $this->hadith, 17);
    bulletinMark($this, $enrollment, $this->fiqh, 15);
    bulletinPupil($this, ['last_name' => 'Autre']);

    app(ArabicReportCardService::class)->generate($this->classroom, $this->term);

    expect(renderBulletin(ArabicReportCard::sole()))
        ->toContain("République de Côte d'Ivoire")
        ->toContain(pdf_rtl('جمهورية كوت ديفوار'))
        ->toContain(pdf_rtl('مدرسة الفلاح الخاصة'))
        ->toContain('BULLETIN DE NOTES 3e')
        ->toContain(pdf_rtl('كشف الدرجات الثالث المتوسط'))
        ->toContain('Sylla Yacoub')
        ->toContain(pdf_rtl('سيلا يعقوب'))
        ->toContain('3e Trimestre')
        ->toContain(pdf_rtl('الفترة الأخيرة'))
        ->toContain('Hadith')
        ->toContain(pdf_rtl('الحديث الشريف'))
        ->toContain('Admis')
        ->toContain('32')
        ->toContain('Effectif : <span class="value">2</span>')
        ->toContain('Abidjan, le')
        ->toContain('Directeur général')
        ->toContain('Président du Jury')
        ->not->toContain('Coéf');
});

test('sans champ en arabe, le bulletin retombe sur le nom latin et le libellé français', function () {
    $this->establishment->update(['name_ar' => null, 'city' => null]);
    $this->term->update(['label_ar' => null]);
    $enrollment = bulletinPupil($this);
    bulletinMark($this, $enrollment, $this->hadith, 12);
    app(ArabicReportCardService::class)->generate($this->classroom, $this->term);

    $html = renderBulletin(ArabicReportCard::sole());

    expect(substr_count($html, 'Sylla Yacoub'))->toBe(2)
        ->and(substr_count($html, '3e Trimestre'))->toBe(2)
        ->and($html)->not->toContain('Abidjan');
});

test('le résultat est Admis à 10 sur 20 et Non admis en dessous', function () {
    $enrollment = bulletinPupil($this);
    bulletinMark($this, $enrollment, $this->hadith, 10);
    $service = app(ArabicReportCardService::class);
    $service->generate($this->classroom, $this->term);

    expect(renderBulletin(ArabicReportCard::sole()))
        ->toContain('<span class="value">Admis</span>')
        ->toContain(pdf_rtl('النتيجة ناجح'));

    ArabicGrade::query()->update(['score' => 9.99]);
    $service->generate($this->classroom, $this->term);

    expect(renderBulletin(ArabicReportCard::sole()->refresh()))
        ->toContain('<span class="value">Non admis</span>')
        ->toContain(pdf_rtl('النتيجة راسب'));
});

test('les matières suivent l’ordre du niveau, une matière hors liste passe à la fin', function () {
    $outside = ArabicSubject::factory()->create(['name' => 'ءءء خارج']);
    ArabicLevelSubject::create(['establishment_id' => $this->establishment->id, 'arabic_level_id' => $this->level->id, 'arabic_subject_id' => $this->fiqh->id, 'sequence' => 1]);
    ArabicLevelSubject::create(['establishment_id' => $this->establishment->id, 'arabic_level_id' => $this->level->id, 'arabic_subject_id' => $this->hadith->id, 'sequence' => 2]);

    $enrollment = bulletinPupil($this);
    foreach ([$outside, $this->hadith, $this->fiqh] as $subject) {
        bulletinMark($this, $enrollment, $subject, 12);
    }

    $service = app(ArabicReportCardService::class);
    $service->generate($this->classroom, $this->term);

    expect($service->subjectBreakdown(ArabicReportCard::sole())->map(fn ($row) => $row->subject->id)->all())
        ->toBe([$this->fiqh->id, $this->hadith->id, $outside->id]);
});

test('l’écran des matières s’ouvre au secondaire, les compositions restent au primaire', function () {
    Livewire::test(LevelSubjects::class)
        ->assertOk()
        ->assertSet('arabic_level_id', $this->level->id)
        ->call('add', $this->fiqh->id)
        ->assertHasNoErrors();

    expect(ArabicLevelSubject::sole()->arabic_subject_id)->toBe($this->fiqh->id);

    Livewire::test(CompositionsIndex::class)->assertForbidden();
});

test('un niveau d’un autre cycle n’est pas accepté sur l’écran des matières', function () {
    $primaryLevel = ArabicLevel::factory()->create(['cycle' => Cycle::Primaire->value]);

    Livewire::test(LevelSubjects::class)
        ->set('arabic_level_id', $primaryLevel->id)
        ->call('add', $this->fiqh->id)
        ->assertNotFound();
});

test('les champs en arabe se saisissent sur l’élève et la période', function () {
    Livewire::test(StudentsIndex::class)
        ->call('create')
        ->set('last_name', 'Sylla')
        ->set('first_name', 'Awa')
        ->set('arabic_name', 'سيلا عوا')
        ->call('save')
        ->assertHasNoErrors();

    expect(Student::where('last_name', 'Sylla')->sole()->arabic_name)->toBe('سيلا عوا');

    Livewire::test(TermsIndex::class)
        ->call('edit', $this->term->id)
        ->assertSet('label_ar', 'الفترة الأخيرة')
        ->set('label_ar', 'الفترة الأولى')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->term->refresh()->label_ar)->toBe('الفترة الأولى');
});

test('le nom arabe et la ville se saisissent sur la fiche de l’établissement', function () {
    $this->establishment->update(['direction_id' => Direction::create(['code' => 'DR-ABJ', 'direction_name' => 'Abidjan'])->id]);
    $this->actingAs(createSaasAdmin());

    Livewire::test(EstablishmentsIndex::class)
        ->call('edit', $this->establishment->id)
        ->assertSet('name_ar', 'مدرسة الفلاح الخاصة')
        ->assertSet('city', 'Abidjan')
        ->set('city', 'Bouaké')
        ->set('name_ar', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->establishment->refresh();

    expect($this->establishment->city)->toBe('Bouaké')
        ->and($this->establishment->name_ar)->toBeNull();
});
