<?php

declare(strict_types=1);

use App\Http\Controllers\Backup\BackupExportController;
use App\Livewire\Backup\Index;
use Illuminate\Support\Facades\Route;

Route::prefix('sauvegarde')->name('backup.')->group(function (): void {
    Route::get('/', Index::class)->name('index');
    Route::get('/export', BackupExportController::class)->name('export');
});
