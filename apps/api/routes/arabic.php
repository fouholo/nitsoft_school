<?php

declare(strict_types=1);

use App\Livewire\Arabic\GradeSheets\Enter as ArabicGradeSheetsEnter;
use App\Livewire\Arabic\GradeSheets\Index as ArabicGradeSheetsIndex;
use App\Livewire\Arabic\Levels\Index as ArabicLevelsIndex;
use App\Livewire\Arabic\Series\Index as ArabicSeriesIndex;
use App\Livewire\Arabic\SubjectCoefficients\Index as ArabicSubjectCoefficientsIndex;
use App\Livewire\Arabic\Subjects\Index as ArabicSubjectsIndex;
use App\Livewire\Arabic\TeacherAssignments\Index as ArabicTeacherAssignmentsIndex;
use App\Livewire\Arabic\Terms\Index as ArabicTermsIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('arabic')->name('arabic.')->group(function (): void {
    Route::get('/levels', ArabicLevelsIndex::class)->name('levels.index');
    Route::get('/series', ArabicSeriesIndex::class)->name('series.index');
    Route::get('/subjects', ArabicSubjectsIndex::class)->name('subjects.index');
    Route::get('/subject-coefficients', ArabicSubjectCoefficientsIndex::class)->name('subject-coefficients.index');
    Route::get('/terms', ArabicTermsIndex::class)->name('terms.index');
    Route::get('/teacher-assignments', ArabicTeacherAssignmentsIndex::class)->name('teacher-assignments.index');
    Route::get('/grade-sheets', ArabicGradeSheetsIndex::class)->name('grade-sheets.index');
    Route::get('/grade-sheets/{gradeSheet}/enter', ArabicGradeSheetsEnter::class)->name('grade-sheets.enter');
});
