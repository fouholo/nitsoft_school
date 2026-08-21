<?php

declare(strict_types=1);

use App\Http\Controllers\Academics\ClassroomIdCardsPdfController;
use App\Http\Controllers\Academics\ClassroomStudentListPdfController;
use App\Http\Controllers\Academics\StudentIdCardPdfController;
use App\Http\Controllers\Billing\FinancialSummaryPdfController;
use App\Http\Controllers\Billing\PaymentReminderPdfController;
use App\Http\Controllers\Billing\PaymentRemindersBatchPdfController;
use App\Livewire\Reports\Index;
use Illuminate\Support\Facades\Route;

Route::prefix('rapports')->name('reports.')->group(function (): void {
    Route::get('/', Index::class)->name('index');
    Route::get('/classes/{classroom}/eleves', ClassroomStudentListPdfController::class)->name('classroom-students-pdf');
    Route::get('/classes/{classroom}/cartes', ClassroomIdCardsPdfController::class)->name('classroom-id-cards-pdf');
    Route::get('/eleves/{student}/carte', StudentIdCardPdfController::class)->name('student-id-card-pdf');
    Route::get('/eleves/{student}/relance', PaymentReminderPdfController::class)->name('payment-reminder-pdf');
    Route::get('/relances', PaymentRemindersBatchPdfController::class)->name('payment-reminders-pdf');
    Route::get('/bilan-financier', FinancialSummaryPdfController::class)->name('financial-summary-pdf');
});
