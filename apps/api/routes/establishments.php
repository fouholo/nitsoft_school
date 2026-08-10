<?php

declare(strict_types=1);

use App\Livewire\Establishments\Index as EstablishmentsIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('establishments')->name('establishments.')->group(function (): void {
    Route::get('/', EstablishmentsIndex::class)->name('index');
});
