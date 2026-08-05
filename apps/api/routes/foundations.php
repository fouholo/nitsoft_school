<?php

declare(strict_types=1);

use App\Livewire\Foundations\Index as FoundationsIndex;
use App\Livewire\Foundations\Show as FoundationsShow;
use Illuminate\Support\Facades\Route;

Route::prefix('foundations')->name('foundations.')->group(function (): void {
    Route::get('/', FoundationsIndex::class)->name('index');
    Route::get('/{foundation}', FoundationsShow::class)->name('show');
});
