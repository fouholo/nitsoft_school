<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoints de synchronisation offline-first (Desktop/Mobile NativePHP)
|--------------------------------------------------------------------------
|
| Implémentés en Phase 3. Montés ici sous /api/v1/sync/* pour poser
| les conventions dès la Phase 0 (voir plan d'architecture, section 5).
|
*/

Route::prefix('sync')->group(function (): void {
    // Route::post('/push', [SyncController::class, 'push']);
    // Route::get('/pull', [SyncController::class, 'pull']);
    // Route::post('/attachments', [SyncController::class, 'storeAttachment']);
});
