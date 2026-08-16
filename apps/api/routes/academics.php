<?php

declare(strict_types=1);

use App\Livewire\Academics\AppreciationScales\Index as AppreciationScalesIndex;
use App\Livewire\Academics\Classrooms\Index as ClassroomsIndex;
use App\Livewire\Academics\PrimarySubjects\Index as PrimarySubjectsIndex;
use App\Livewire\Academics\SchoolYears\Index as SchoolYearsIndex;
use App\Livewire\Academics\SubjectCoefficients\Index as SubjectCoefficientsIndex;
use App\Livewire\Academics\Subjects\Index as SubjectsIndex;
use App\Livewire\Academics\TeacherAssignments\Index as TeacherAssignmentsIndex;
use App\Livewire\Academics\Terms\Index as TermsIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('academics')->name('academics.')->group(function (): void {
    Route::get('/school-years', SchoolYearsIndex::class)->name('school-years.index');
    Route::get('/terms', TermsIndex::class)->name('terms.index');
    Route::get('/classrooms', ClassroomsIndex::class)->name('classrooms.index');
    Route::get('/subjects', SubjectsIndex::class)->name('subjects.index');
    Route::get('/primary-subjects', PrimarySubjectsIndex::class)->name('primary-subjects.index');
    Route::get('/appreciation-scales', AppreciationScalesIndex::class)->name('appreciation-scales.index');
    Route::get('/subject-coefficients', SubjectCoefficientsIndex::class)->name('subject-coefficients.index');
    Route::get('/teacher-assignments', TeacherAssignmentsIndex::class)->name('teacher-assignments.index');
});
