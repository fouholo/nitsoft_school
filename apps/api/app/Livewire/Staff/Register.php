<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Register extends Component
{
    public string $name = '';

    public string $first_name = '';

    public string $email = '';

    public string $pseudo = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $uid = '';

    public string $role = '';

    public bool $pendingApproval = false;

    public function register(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'pseudo' => User::pseudoRules(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'uid' => ['required', 'string'],
            'role' => ['required', Rule::in(['fondateur', 'directeur', 'gestionnaire'])],
        ]);

        // Un fondateur qui rejoint un groupe scolaire peut saisir directement
        // l'UID de la fondation (préfixe 210, distinct du 211 des
        // établissements) plutôt que celui d'un établissement du groupe.
        if ($data['role'] === 'fondateur') {
            $foundation = Foundation::where('uid_serveur', $data['uid'])->first();

            if ($foundation !== null) {
                [$user, $pendingApproval] = DB::transaction(fn () => $this->registerOnFoundation($data, $foundation));

                $this->finalizeRegistration($user, $pendingApproval);

                return;
            }
        }

        $establishment = Establishment::where('uid_serveur', $data['uid'])->first();

        if ($establishment === null) {
            $this->addError('uid', __('Aucun établissement ni fondation ne correspond à cet identifiant.'));

            return;
        }

        [$user, $pendingApproval] = DB::transaction(function () use ($data, $establishment) {
            if ($data['role'] === 'fondateur' && $establishment->foundation_id === null) {
                return $this->registerOnEstablishment($data, $establishment, 'is_general_admin');
            }

            if ($data['role'] === 'fondateur') {
                return $this->registerOnFoundation($data, $establishment->foundation()->firstOrFail());
            }

            return $this->registerOnEstablishment($data, $establishment, 'is_local_admin');
        });

        $this->finalizeRegistration($user, $pendingApproval);
    }

    private function finalizeRegistration(User $user, bool $pendingApproval): void
    {
        if ($pendingApproval) {
            $this->pendingApproval = true;

            return;
        }

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('home', navigate: true);
    }

    /**
     * @param  array{name: string, first_name: string, email: string, pseudo: string, password: string, role: string}  $data
     * @return array{0: User, 1: bool}
     */
    private function registerOnEstablishment(array $data, Establishment $establishment, string $flagColumn): array
    {
        $alreadyTaken = EstablishmentUserPivot::where('establishment_id', $establishment->id)
            ->where($flagColumn, true)
            ->lockForUpdate()
            ->exists();

        $user = User::create([
            'name' => $data['name'],
            'first_name' => $data['first_name'],
            'email' => $data['email'],
            'pseudo' => $data['pseudo'],
            'password' => $data['password'],
        ]);

        $attributes = [
            'establishment_id' => $establishment->id,
            'user_id' => $user->id,
            'role' => $data['role'],
            'is_active' => ! $alreadyTaken,
            $flagColumn => $alreadyTaken ? null : true,
        ];

        try {
            EstablishmentUserPivot::create($attributes);
        } catch (QueryException $e) {
            $needle = $flagColumn === 'is_general_admin' ? 'general_admin' : 'local_admin';

            if (! str_contains($e->getMessage(), $needle)) {
                throw $e;
            }

            $attributes['is_active'] = false;
            $attributes[$flagColumn] = null;
            EstablishmentUserPivot::create($attributes);
            $alreadyTaken = true;
        }

        return [$user, $alreadyTaken];
    }

    /**
     * @param  array{name: string, first_name: string, email: string, pseudo: string, password: string, role: string}  $data
     * @return array{0: User, 1: bool}
     */
    private function registerOnFoundation(array $data, Foundation $foundation): array
    {
        $alreadyTaken = FoundationUserPivot::where('foundation_id', $foundation->id)
            ->where('is_general_admin', true)
            ->lockForUpdate()
            ->exists();

        $user = User::create([
            'name' => $data['name'],
            'first_name' => $data['first_name'],
            'email' => $data['email'],
            'pseudo' => $data['pseudo'],
            'password' => $data['password'],
        ]);

        $attributes = [
            'foundation_id' => $foundation->id,
            'user_id' => $user->id,
            'role' => $data['role'],
            'is_active' => ! $alreadyTaken,
            'is_general_admin' => $alreadyTaken ? null : true,
        ];

        try {
            FoundationUserPivot::create($attributes);
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'general_admin')) {
                throw $e;
            }

            $attributes['is_active'] = false;
            $attributes['is_general_admin'] = null;
            FoundationUserPivot::create($attributes);
            $alreadyTaken = true;
        }

        return [$user, $alreadyTaken];
    }

    public function render()
    {
        return view('livewire.staff.register');
    }
}
