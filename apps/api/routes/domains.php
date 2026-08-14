<?php

declare(strict_types=1);

use App\Livewire\Domains\Index;
use Illuminate\Support\Facades\Route;

Route::prefix('domains')->name('domains.')->group(function (): void {
    Route::get('/', Index::class)->name('index');
});
