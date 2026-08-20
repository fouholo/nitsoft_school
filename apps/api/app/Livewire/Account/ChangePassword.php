<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mot de passe')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $changed = false;

    public function save(): void
    {
        $this->changed = false;

        $data = $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [], [
            'current_password' => 'mot de passe actuel',
            'password' => 'nouveau mot de passe',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update(['password' => $data['password']]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->changed = true;
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.account.change-password')
            ->layout($user->guardianProfile ? 'layouts.guardian-portal' : 'layouts.app');
    }
}
