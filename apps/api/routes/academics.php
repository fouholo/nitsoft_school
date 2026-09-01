<?php

declare(strict_types=1);

use App\Livewire\Academics\AppreciationScales\Index as AppreciationScalesIndex;
use App\Livewire\Academics\Classrooms\Index as ClassroomsIndex;
use App\Livewire\Academics\Levels\Index as LevelsIndex;
use App\Livewire\Academics\PrimarySubjects\Index as PrimarySubjectsIndex;
use App\Livewire\Academics\SchoolYears\Index as SchoolYearsIndex;
use App\Livewire\Academics\SubjectCoefficients\Index as SubjectCoefficientsIndex;
use App\Livewire\Academics\Subjects\Index as SubjectsIndex;
use App\Livewire\Academics\TeacherAssignments\Index as TeacherAssignmentsIndex;
use App\Livewire\Academics\Terms\Index as TermsIndex;
use App\Livewire\Academics\Timetable\Browse as TimetableBrowse;
use App\Livewire\Academics\Timetable\Index as TimetableIndex;
use App\Livewire\Academics\Timetable\MySchedule as TimetableMySchedule;
use App\Livewire\Academics\Timetable\SlotsIndex as TimetableSlotsIndex;
use App\Livewire\Academics\Timetable\TeacherSchedule as TimetableTeacherSchedule;
use Illuminate\Support\Facades\Route;

Route::prefix('academics')->name('academics.')->group(function (): void {
    Route::get('/school-years', SchoolYearsIndex::class)->name('school-years.index');
    Route::get('/terms', TermsIndex::class)->name('terms.index');
    Route::get('/classrooms', ClassroomsIndex::class)->name('classrooms.index');
    Route::get('/levels', LevelsIndex::class)->name('levels.index');
    Route::get('/subjects', SubjectsIndex::class)->name('subjects.index');
    Route::get('/primary-subjects', PrimarySubjectsIndex::class)->name('primary-subjects.index');
    Route::get('/appreciation-scales', AppreciationScalesIndex::class)->name('appreciation-scales.index');
    Route::get('/subject-coefficients', SubjectCoefficientsIndex::class)->name('subject-coefficients.index');
    Route::get('/teacher-assignments', TeacherAssignmentsIndex::class)->name('teacher-assignments.index');
    Route::get('/timetable/slots', TimetableSlotsIndex::class)->name('timetable.slots.index');
    Route::get('/timetable/mine', TimetableMySchedule::class)->name('timetable.mine');
    Route::get('/timetable/teachers/{user}', TimetableTeacherSchedule::class)->name('timetable.teachers.show');
    Route::get('/timetable', TimetableBrowse::class)->name('timetable.browse');
    Route::get('/timetable/{classroom}', TimetableIndex::class)->name('timetable.index');
});
