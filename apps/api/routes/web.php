<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    /** @var User $user */
    $user = auth()->user();

    return redirect()->route($user->currentRole() === 'parent' ? 'guardian-portal.dashboard' : 'dashboard');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    require __DIR__.'/academics.php';
    require __DIR__.'/enrollment.php';
    require __DIR__.'/grading.php';
    require __DIR__.'/attendance.php';
    require __DIR__.'/billing.php';
    require __DIR__.'/notifications.php';
    require __DIR__.'/guardian-portal.php';
    require __DIR__.'/foundations.php';
});
