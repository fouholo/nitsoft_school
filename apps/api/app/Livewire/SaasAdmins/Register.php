<?php

declare(strict_types=1);

namespace App\Livewire\SaasAdmins;

use App\Domain\Establishments\Enums\SaasAdminType;
use App\Domain\Establishments\Models\SaasAdmin;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (SaasAdmin::where('is_main', true)->exists()) {
            $this->redirectRoute('login', navigate: true);
        }
    }

    public function register(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $mainAdmin = DB::transaction(function () use ($data): SaasAdmin {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);

                return SaasAdmin::create([
                    'user_id' => $user->id,
                    'type' => SaasAdminType::Main,
                    'is_active' => true,
                    'is_main' => true,
                ]);
            });
        } catch (QueryException) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        Auth::login($mainAdmin->user);
        session()->regenerate();

        $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.saas-admins.register');
    }
}
