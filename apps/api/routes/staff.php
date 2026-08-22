<?php

declare(strict_types=1);

use App\Livewire\Staff\Index as StaffIndex;
use App\Livewire\Staff\ManageOrganization;
use App\Livewire\Staff\Show as StaffShow;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/organisation', ManageOrganization::class)->name('organization');
});

Route::get('/etablissements/{establishment}/staff', StaffIndex::class)->name('staff.index');
Route::get('/etablissements/{establishment}/staff/{pivot}', StaffShow::class)->name('staff.show');
