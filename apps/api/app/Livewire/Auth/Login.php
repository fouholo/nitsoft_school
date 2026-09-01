<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $identifiant = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'identifiant' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = filter_var($this->identifiant, FILTER_VALIDATE_EMAIL) !== false
            ? User::where('email', $this->identifiant)->first()
            : User::whereRaw('LOWER(pseudo) = ?', [mb_strtolower($this->identifiant)])->first();

        if ($user === null || ! Auth::attempt(['email' => $user->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'identifiant' => __('Ces identifiants ne correspondent à aucun compte.'),
            ]);
        }

        session()->regenerate();

        $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
