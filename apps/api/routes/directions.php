<?php

declare(strict_types=1);

use App\Livewire\Directions\Index;
use Illuminate\Support\Facades\Route;

Route::prefix('directions')->name('directions.')->group(function (): void {
    Route::get('/', Index::class)->name('index');
});
