<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\PaymentReceiptPdfController;
use App\Livewire\Billing\FeeSchedules\Index as FeeSchedulesIndex;
use App\Livewire\Billing\Invoices\Index as InvoicesIndex;
use App\Livewire\Billing\Invoices\Show as InvoicesShow;
use Illuminate\Support\Facades\Route;

Route::prefix('billing')->name('billing.')->group(function (): void {
    Route::get('/fee-schedules', FeeSchedulesIndex::class)->name('fee-schedules.index');
    Route::get('/invoices', InvoicesIndex::class)->name('invoices.index');
    Route::get('/invoices/{invoice}', InvoicesShow::class)->name('invoices.show');
    Route::get('/payments/{payment}/receipt', PaymentReceiptPdfController::class)->name('payments.receipt');
});
