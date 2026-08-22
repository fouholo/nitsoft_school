<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Account\ChangePassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Dashboard;
use App\Livewire\SaasAdmins\Register as SaasAdminsRegister;
use App\Livewire\Staff\Register as StaffRegister;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    /** @var User $user */
    $user = auth()->user();

    if ($user->isSaasAdmin()) {
        return redirect()->route('foundations.index');
    }

    return redirect()->route($user->guardianProfile ? 'guardian-portal.dashboard' : 'dashboard');
})->name('home');

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, config('app.supported_locales'), true), 404);

    if (auth()->check()) {
        auth()->user()->update(['locale' => $locale]);
    } else {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/saas-admin/register', SaasAdminsRegister::class)->name('saas-admins.register');
    Route::get('/staff/register', StaffRegister::class)->name('staff.register');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/mon-compte/mot-de-passe', ChangePassword::class)->name('account.password.edit');

    require __DIR__.'/academics.php';
    require __DIR__.'/arabic.php';
    require __DIR__.'/enrollment.php';
    require __DIR__.'/reports.php';
    require __DIR__.'/grading.php';
    require __DIR__.'/attendance.php';
    require __DIR__.'/billing.php';
    require __DIR__.'/notifications.php';
    require __DIR__.'/messaging.php';
    require __DIR__.'/guardian-portal.php';
    require __DIR__.'/foundations.php';
    require __DIR__.'/establishments.php';
    require __DIR__.'/inspections.php';
    require __DIR__.'/directions.php';
    require __DIR__.'/domains.php';
    require __DIR__.'/general-information.php';
    require __DIR__.'/saas-admins.php';
    require __DIR__.'/staff.php';
});
