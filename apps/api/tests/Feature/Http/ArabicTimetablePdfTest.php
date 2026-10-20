<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\ArabicTimetableSlot;
use App\Domain\Timetable\Services\WeeklyAgenda;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true, 'arabic_timetable_days' => ['monday', 'saturday']]);
    actingInEstablishment($this->establishment);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->arabicClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => ArabicLevel::factory()->create(['wording' => 'الصف الأول'])->id,
        'numero' => '1',
    ]);
    $this->teacher = createUserWithRole($this->establishment, 'enseignant');

    $slot = ArabicTimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'ح1', 'start_time' => '15:00', 'end_time' => '16:00', 'sequence' => 1]);
    ArabicTimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'Pause', 'start_time' => '16:00', 'end_time' => '16:15', 'sequence' => 2, 'is_break' => true]);

    ArabicTimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
        'arabic_subject_id' => ArabicSubject::factory()->create(['name' => 'القرآن الكريم'])->id,
        'user_id' => $this->teacher->id,
        'day_of_week' => 'saturday',
        'arabic_timetable_slot_id' => $slot->id,
        'room' => 'B2',
    ]);
});

test('le PDF de l’emploi du temps d’une classe arabe s’ouvre et se télécharge', function () {
    $directeur = createUserWithRole($this->establishment, 'directeur');

    $inline = $this->actingAs($directeur)->get(route('reports.arabic-timetable-classroom-pdf', $this->arabicClassroom));
    $inline->assertOk();
    expect($inline->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($inline->headers->get('Content-Disposition'))->toContain('inline');

    $download = $this->actingAs($directeur)->get(route('reports.arabic-timetable-classroom-pdf', ['arabicClassroom' => $this->arabicClassroom, 'download' => 1]));
    expect($download->headers->get('Content-Disposition'))->toContain('attachment')->toContain('emploi-du-temps-arabe-');
});

test('le PDF d’une classe arabe d’une autre école est introuvable', function () {
    $other = Establishment::factory()->create(['is_arabe' => true]);
    $directeur = createUserWithRole($other, 'directeur');
    actingInEstablishment($other);

    $this->actingAs($directeur)
        ->get(route('reports.arabic-timetable-classroom-pdf', $this->arabicClassroom))
        ->assertNotFound();
});

test('le document est en arabe : jours arabes du dernier au premier, créneaux à droite, pause et matières', function () {
    $html = view('pdf.arabic-timetable', [
        'arabicClassroom' => $this->arabicClassroom->loadMissing(['arabicLevel', 'arabicSerie', 'schoolYear', 'establishment.inspection.direction']),
        'establishment' => $this->establishment,
        'generalInformation' => GeneralInformation::current(),
        'days' => $this->establishment->arabicTimetableDays(),
        'slots' => ArabicTimetableSlot::orderBy('sequence')->get(),
        'sessions' => ArabicTimetableSession::with(['arabicSubject', 'teacher'])->get()
            ->keyBy(fn (ArabicTimetableSession $session) => $session->day_of_week->value.'-'.$session->arabic_timetable_slot_id),
        'fontRegularPath' => '',
        'fontBoldPath' => '',
    ])->render();

    // Colonnes écrites dans l'ordre visuel : samedi (السبت) avant lundi
    // (الإثنين), puis la colonne des créneaux (الحصة) à droite.
    expect(strpos($html, 'السبت'))->toBeLessThan(strpos($html, 'الإثنين'))
        ->and(strpos($html, 'الإثنين'))->toBeLessThan(strpos($html, 'الحصة'))
        ->and($html)->toContain(pdf_rtl('جدول الحصص — الصف الأول 1'))
        ->toContain(pdf_rtl('القرآن الكريم'))
        ->toContain(pdf_rtl('القاعة B2'))
        ->toContain('استراحة')
        ->toContain('المدير')
        ->toContain($this->teacher->name);
});

test('le PDF « Mon emploi du temps » d’un enseignant d’école franco-arabe présente l’agenda des deux filières', function () {
    $response = $this->actingAs($this->teacher)->get(route('reports.timetable-mine-pdf'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('l’agenda PDF reprend les séances arabes avec leurs jours', function () {
    $html = view('pdf.timetable-agenda', [
        'title' => 'Emploi du temps — Test',
        'establishment' => $this->establishment->loadMissing('inspection.direction'),
        'generalInformation' => GeneralInformation::current(),
        'agenda' => app(WeeklyAgenda::class)->forTeacher($this->teacher->id, $this->establishment),
        'fontRegularPath' => '',
        'fontBoldPath' => '',
    ])->render();

    expect($html)->toContain(__('Samedi'))
        ->toContain(pdf_rtl('القرآن الكريم'))
        ->toContain('15:00–16:00')
        ->toContain($this->arabicClassroom->name);
});
