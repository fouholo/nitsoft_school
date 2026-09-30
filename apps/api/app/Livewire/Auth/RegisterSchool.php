<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Inspection;
use App\Domain\Establishments\Models\SchoolRegistration;
use App\Livewire\Concerns\ThrottlesSubmissions;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Je crée mon école » : un fondateur dépose une demande d'inscription
 * (compte + école, éventuellement groupe scolaire). Rien n'est créé avant
 * validation par un administrateur SaaS — voir SchoolRegistrationApprover.
 */
#[Layout('layouts.guest')]
class RegisterSchool extends Component
{
    use ThrottlesSubmissions;

    public string $name = '';

    public string $first_name = '';

    public string $email = '';

    public string $pseudo = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $establishment_name = '';

    public string $establishment_type = '';

    public string $inspection_id = '';

    public string $direction_id = '';

    public string $phone = '';

    public string $address = '';

    public bool $has_foundation = false;

    public string $foundation_name = '';

    public bool $submitted = false;

    public function register(): void
    {
        $this->throttle('register-school', 5, 600, 'email');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:school_registrations,email'],
            'pseudo' => [...User::pseudoRules(), 'unique:school_registrations,pseudo'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'establishment_name' => ['required', 'string', 'max:255'],
            'establishment_type' => ['required', Rule::enum(EstablishmentType::class)],
            'inspection_id' => [
                Rule::requiredIf($this->establishment_type === EstablishmentType::PrescolairePrimaire->value),
                'nullable', 'integer', 'exists:inspections,id',
            ],
            'direction_id' => [
                Rule::requiredIf($this->establishment_type === EstablishmentType::Secondaire->value),
                'nullable', 'integer', 'exists:directions,id',
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'has_foundation' => ['boolean'],
            'foundation_name' => [Rule::requiredIf($this->has_foundation), 'nullable', 'string', 'max:255'],
        ], [], [
            'name' => __('Nom'),
            'first_name' => __('Prénom'),
            'email' => __('Adresse e-mail'),
            'pseudo' => __('Pseudo'),
            'password' => __('Mot de passe'),
            'establishment_name' => __("Nom de l'école"),
            'establishment_type' => __('Type'),
            'inspection_id' => __('Inspection'),
            'direction_id' => __('Direction'),
            'phone' => __('Téléphone'),
            'address' => __('Adresse'),
            'foundation_name' => __('Nom du groupe scolaire'),
        ]);

        // Inspection (primaire) et direction (secondaire) sont exclusives —
        // même règle que App\Livewire\Establishments\Index::save().
        $isPrimary = $data['establishment_type'] === EstablishmentType::PrescolairePrimaire->value;

        SchoolRegistration::create([
            'name' => $data['name'],
            'first_name' => $data['first_name'],
            'email' => $data['email'],
            'pseudo' => $data['pseudo'],
            'password' => $data['password'],
            'establishment_name' => $data['establishment_name'],
            'establishment_type' => $data['establishment_type'],
            'inspection_id' => $isPrimary ? $data['inspection_id'] : null,
            'direction_id' => $isPrimary ? null : $data['direction_id'],
            'phone' => $data['phone'] !== '' ? $data['phone'] : null,
            'address' => $data['address'] !== '' ? $data['address'] : null,
            'foundation_name' => $data['has_foundation'] ? $data['foundation_name'] : null,
        ]);

        $this->reset(['password', 'password_confirmation']);
        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.auth.register-school', [
            'types' => EstablishmentType::cases(),
            'inspections' => $this->establishment_type === EstablishmentType::PrescolairePrimaire->value
                ? Inspection::orderBy('inspection_name')->get()
                : collect(),
            'directions' => $this->establishment_type === EstablishmentType::Secondaire->value
                ? Direction::orderBy('direction_name')->get()
                : collect(),
        ])->title(__('Créer mon école'));
    }
}
