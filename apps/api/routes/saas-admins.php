<?php

declare(strict_types=1);

use App\Livewire\SaasAdmins\Index as SaasAdminsIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('saas-admins')->name('saas-admins.')->group(function (): void {
    Route::get('/', SaasAdminsIndex::class)->name('index');
});
