<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\PaymentReceiptPdfController;
use App\Livewire\Billing\Expenses\Index as ExpensesIndex;
use App\Livewire\Billing\Invoices\Index as InvoicesIndex;
use App\Livewire\Billing\Invoices\Show as InvoicesShow;
use App\Livewire\Billing\PaymentTracking\Index as PaymentTrackingIndex;
use App\Livewire\Billing\TuitionFees\Index as TuitionFeesIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('billing')->name('billing.')->group(function (): void {
    Route::get('/tuition-fees', TuitionFeesIndex::class)->name('tuition-fees.index');
    Route::get('/invoices', InvoicesIndex::class)->name('invoices.index');
    Route::get('/invoices/{invoice}', InvoicesShow::class)->name('invoices.show');
    Route::get('/payments/{payment}/receipt', PaymentReceiptPdfController::class)->name('payments.receipt');
    Route::get('/expenses', ExpensesIndex::class)->name('expenses.index');
    Route::get('/payment-tracking', PaymentTrackingIndex::class)->name('payment-tracking.index');
});
