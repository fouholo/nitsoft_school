<?php

declare(strict_types=1);

use App\Livewire\Inspections\Index;
use Illuminate\Support\Facades\Route;

Route::prefix('inspections')->name('inspections.')->group(function (): void {
    Route::get('/', Index::class)->name('index');
});
