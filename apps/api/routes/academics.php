<?php

declare(strict_types=1);

use App\Livewire\Academics\Classrooms\Index as ClassroomsIndex;
use App\Livewire\Academics\SchoolYears\Index as SchoolYearsIndex;
use App\Livewire\Academics\Subjects\Index as SubjectsIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('academics')->name('academics.')->group(function (): void {
    Route::get('/school-years', SchoolYearsIndex::class)->name('school-years.index');
    Route::get('/classrooms', ClassroomsIndex::class)->name('classrooms.index');
    Route::get('/subjects', SubjectsIndex::class)->name('subjects.index');
});
