<?php

declare(strict_types=1);

use App\Livewire\Arabic\Levels\Index as ArabicLevelsIndex;
use App\Livewire\Arabic\Series\Index as ArabicSeriesIndex;
use App\Livewire\Arabic\SubjectCoefficients\Index as ArabicSubjectCoefficientsIndex;
use App\Livewire\Arabic\Subjects\Index as ArabicSubjectsIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('arabic')->name('arabic.')->group(function (): void {
    Route::get('/levels', ArabicLevelsIndex::class)->name('levels.index');
    Route::get('/series', ArabicSeriesIndex::class)->name('series.index');
    Route::get('/subjects', ArabicSubjectsIndex::class)->name('subjects.index');
    Route::get('/subject-coefficients', ArabicSubjectCoefficientsIndex::class)->name('subject-coefficients.index');
});
