<?php

declare(strict_types=1);

use App\Livewire\GuardianPortal\Dashboard as GuardianDashboard;
use App\Livewire\GuardianPortal\LinkChild;
use App\Livewire\GuardianPortal\StudentAttendance;
use App\Livewire\GuardianPortal\StudentBilling;
use App\Livewire\GuardianPortal\StudentGrades;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('guardian-portal.')->group(function (): void {
    Route::get('/', GuardianDashboard::class)->name('dashboard');
    Route::get('/link-child', LinkChild::class)->name('link-child');
    Route::get('/students/{student}/grades', StudentGrades::class)->name('students.grades');
    Route::get('/students/{student}/attendance', StudentAttendance::class)->name('students.attendance');
    Route::get('/students/{student}/billing', StudentBilling::class)->name('students.billing');
});
