<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Domain\Establishments\Models\SchoolRegistration;
use App\Livewire\Concerns\ThrottlesSubmissions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    use ThrottlesSubmissions;

    public string $identifiant = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'identifiant' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 5 essais par minute pour un même identifiant depuis une même
        // adresse : freine la recherche de mot de passe, en particulier du
        // mot de passe par défaut des comptes fraîchement créés.
        $throttleKey = mb_strtolower($this->identifiant).'|'.request()->ip();
        $this->throttle('login', 5, 60, 'identifiant', $throttleKey);

        $user = filter_var($this->identifiant, FILTER_VALIDATE_EMAIL) !== false
            ? User::where('email', $this->identifiant)->first()
            : User::whereRaw('LOWER(pseudo) = ?', [mb_strtolower($this->identifiant)])->first();

        if ($user === null && $this->hasPendingSchoolRegistration()) {
            throw ValidationException::withMessages([
                'identifiant' => __("Votre inscription est en attente de validation par l'équipe Nitsoft."),
            ]);
        }

        if ($user === null || ! Auth::attempt(['email' => $user->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'identifiant' => __('Ces identifiants ne correspondent à aucun compte.'),
            ]);
        }

        $this->clearThrottle('login', $throttleKey);

        session()->regenerate();

        $this->redirectRoute('home', navigate: true);
    }

    /**
     * Un fondateur dont la demande d'inscription n'est pas encore validée
     * n'a pas de compte : on le lui signale, mais seulement s'il connaît le
     * mot de passe de la demande (pas de fuite sur l'existence d'une demande).
     */
    private function hasPendingSchoolRegistration(): bool
    {
        $registration = filter_var($this->identifiant, FILTER_VALIDATE_EMAIL) !== false
            ? SchoolRegistration::where('email', $this->identifiant)->first()
            : SchoolRegistration::whereRaw('LOWER(pseudo) = ?', [mb_strtolower($this->identifiant)])->first();

        return $registration !== null && Hash::check($this->password, $registration->password);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
