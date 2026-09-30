<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Livewire\Concerns\ThrottlesSubmissions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Auto-inscription d'un fondateur, directeur ou gestionnaire sur un
 * établissement ou une fondation existant, à partir de son UID.
 *
 * L'UID est séquentiel, donc devinable : il ne vaut pas preuve
 * d'appartenance. Le compte est toujours créé inactif et sans pouvoir
 * d'administration ; il est activé par un administrateur de l'organisation
 * (Staff\ManageOrganization, Staff\Index) ou, si elle n'en a pas encore, par
 * un administrateur SaaS (StaffRegistrationReviewer, écran Établissements).
 */
#[Layout('layouts.guest')]
class Register extends Component
{
    use ThrottlesSubmissions;

    public string $name = '';

    public string $first_name = '';

    public string $email = '';

    public string $pseudo = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $uid = '';

    // Présélection depuis la page de choix d'inscription (?role=fondateur) ;
    // la validation de register() reste la seule barrière sur la valeur.
    #[Url]
    public string $role = '';

    public bool $pendingApproval = false;

    public function register(): void
    {
        $this->throttle('staff-register', 5, 600, 'uid');

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
                DB::transaction(fn () => $this->registerOnFoundation($data, $foundation));

                $this->pendingApproval = true;

                return;
            }
        }

        $establishment = Establishment::where('uid_serveur', $data['uid'])->first();

        if ($establishment === null) {
            $this->addError('uid', __('Aucun établissement ni fondation ne correspond à cet identifiant.'));

            return;
        }

        DB::transaction(function () use ($data, $establishment): void {
            if ($data['role'] === 'fondateur' && $establishment->foundation_id !== null) {
                $this->registerOnFoundation($data, $establishment->foundation()->firstOrFail());

                return;
            }

            $this->registerOnEstablishment($data, $establishment);
        });

        $this->pendingApproval = true;
    }

    /**
     * @param  array{name: string, first_name: string, email: string, pseudo: string, password: string, role: string}  $data
     */
    private function registerOnEstablishment(array $data, Establishment $establishment): void
    {
        EstablishmentUserPivot::create([
            'establishment_id' => $establishment->id,
            'user_id' => $this->createUser($data)->id,
            'role' => $data['role'],
            'is_active' => false,
        ]);
    }

    /**
     * @param  array{name: string, first_name: string, email: string, pseudo: string, password: string, role: string}  $data
     */
    private function registerOnFoundation(array $data, Foundation $foundation): void
    {
        FoundationUserPivot::create([
            'foundation_id' => $foundation->id,
            'user_id' => $this->createUser($data)->id,
            'role' => $data['role'],
            'is_active' => false,
        ]);
    }

    /**
     * @param  array{name: string, first_name: string, email: string, pseudo: string, password: string, role: string}  $data
     */
    private function createUser(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'first_name' => $data['first_name'],
            'email' => $data['email'],
            'pseudo' => $data['pseudo'],
            'password' => $data['password'],
        ]);
    }

    public function render()
    {
        return view('livewire.staff.register');
    }
}
