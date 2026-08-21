<?php

declare(strict_types=1);

use App\Http\Controllers\Academics\ClassroomIdCardsPdfController;
use App\Http\Controllers\Academics\ClassroomStudentListPdfController;
use App\Http\Controllers\Academics\StudentIdCardPdfController;
use App\Livewire\Reports\Index;
use Illuminate\Support\Facades\Route;

Route::prefix('rapports')->name('reports.')->group(function (): void {
    Route::get('/', Index::class)->name('index');
    Route::get('/classes/{classroom}/eleves', ClassroomStudentListPdfController::class)->name('classroom-students-pdf');
    Route::get('/classes/{classroom}/cartes', ClassroomIdCardsPdfController::class)->name('classroom-id-cards-pdf');
    Route::get('/eleves/{student}/carte', StudentIdCardPdfController::class)->name('student-id-card-pdf');
});
