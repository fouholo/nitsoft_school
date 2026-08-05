<?php

declare(strict_types=1);

use App\Livewire\Guardians\Index as GuardiansIndex;
use App\Livewire\Students\Index as StudentsIndex;
use App\Livewire\Students\Show as StudentsShow;
use Illuminate\Support\Facades\Route;

Route::get('/students', StudentsIndex::class)->name('students.index');
Route::get('/students/{student}', StudentsShow::class)->name('students.show');
Route::get('/guardians', GuardiansIndex::class)->name('guardians.index');
