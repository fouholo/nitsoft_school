<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant.token'])->group(function (): void {
    require __DIR__.'/sync.php';
});
