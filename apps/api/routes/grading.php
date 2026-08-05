<?php

declare(strict_types=1);

use App\Livewire\Grading\GradeSheets\Enter as GradeSheetsEnter;
use App\Livewire\Grading\GradeSheets\Index as GradeSheetsIndex;
use App\Livewire\Grading\ReportCards\Index as ReportCardsIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('grading')->name('grading.')->group(function (): void {
    Route::get('/grade-sheets', GradeSheetsIndex::class)->name('grade-sheets.index');
    Route::get('/grade-sheets/{gradeSheet}/enter', GradeSheetsEnter::class)->name('grade-sheets.enter');
    Route::get('/report-cards', ReportCardsIndex::class)->name('report-cards.index');
});
