<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

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
            // Le mot de passe par défaut ne peut pas être « choisi » à
            // nouveau : ce serait contourner le changement obligatoire.
            'password' => ['required', 'confirmed', Password::min(8), Rule::notIn([User::DEFAULT_PASSWORD])],
        ], [], [
            'current_password' => __('mot de passe actuel'),
            'password' => __('nouveau mot de passe'),
        ]);

        /** @var User $user */
        $user = Auth::user();
        $wasForced = $user->must_change_password;

        $user->update(['password' => $data['password'], 'must_change_password' => false]);

        if ($wasForced) {
            $this->redirectRoute('home', navigate: true);

            return;
        }

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->changed = true;
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.account.change-password', [
            'forced' => $user->must_change_password,
        ])
            ->layout($user->guardianProfile ? 'layouts.guardian-portal' : 'layouts.app')
            ->title(__('Mot de passe'));
    }
}
