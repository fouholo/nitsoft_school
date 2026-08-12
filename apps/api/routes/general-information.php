<?php

declare(strict_types=1);

use App\Livewire\GeneralInformation\Edit;
use Illuminate\Support\Facades\Route;

Route::get('/informations-generales', Edit::class)->name('general-information.edit');
