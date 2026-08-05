<?php

declare(strict_types=1);

use App\Livewire\Attendance\Sessions\Index as SessionsIndex;
use App\Livewire\Attendance\Sessions\Mark as SessionsMark;
use Illuminate\Support\Facades\Route;

Route::prefix('attendance')->name('attendance.')->group(function (): void {
    Route::get('/sessions', SessionsIndex::class)->name('sessions.index');
    Route::get('/sessions/{session}/mark', SessionsMark::class)->name('sessions.mark');
});
