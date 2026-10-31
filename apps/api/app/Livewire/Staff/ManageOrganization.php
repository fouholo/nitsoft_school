<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Domain\Academics\Models\Teacher;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Enums\PdfHeaderStyle;
use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Domain\Establishments\Models\Inspection;
use App\Domain\Establishments\Models\Role;
use App\Domain\Establishments\Support\UniqueSlug;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ManageOrganization extends Component
{
    use WithFileUploads;

    /**
     * Rôles que le fondateur/GENERAL_ADMIN peut attribuer depuis cet écran —
     * "fondateur" (auto-inscription/addFounder) et "parent" (liaison tuteur)
     * ont leurs propres flux dédiés et ne figurent pas ici.
     *
     * @var list<string>
     */
    private const ASSIGNABLE_ROLES = ['directeur', 'gestionnaire', 'enseignant', 'caissier', 'educateur'];

    public Foundation|Establishment $organization;

    public string $staff_name = '';

    public string $staff_email = '';

    public string $staff_role = 'enseignant';

    /**
     * Écoles cochées à la création du compte (fondation) — voir
     * docs/superpowers/specs/2026-10-31-personnel-plusieurs-ecoles-design.md.
     *
     * @var array<int, int|string>
     */
    public array $staff_establishment_ids = [];

    /**
     * Ligne de la personne qu'on ajoute à d'autres écoles.
     */
    public ?int $addingPivotId = null;

    public string $add_role = '';

    /**
     * @var array<int, int|string>
     */
    public array $add_establishment_ids = [];

    public ?string $generatedPassword = null;

    public ?string $generatedPasswordFor = null;

    public string $new_establishment_name = '';

    public string $new_establishment_type = '';

    public string $new_establishment_address = '';

    public string $new_establishment_phone = '';

    public string $new_establishment_inspection_id = '';

    public string $new_establishment_direction_id = '';

    public string $new_establishment_opening_code = '';

    public string $new_establishment_dsps_code = '';

    public string $new_establishment_latitude = '';

    public string $new_establishment_longitude = '';

    public string $new_establishment_email = '';

    public bool $new_establishment_is_arabe = false;

    public ?TemporaryUploadedFile $new_establishment_logo = null;

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $organization = $this->resolveOrganizationFor($user);

        abort_if($organization === null, 403);

        $this->organization = $organization;
    }

    public function create(): void
    {
        $this->authorize('manageOrganization', $this->organization);

        $this->generatedPassword = null;
        $this->generatedPasswordFor = null;

        $establishmentIds = $this->organizationEstablishmentIds();

        // Une école indépendante n'a rien à cocher : son école est prise d'office.
        if ($this->organization instanceof Establishment) {
            $this->staff_establishment_ids = [$this->organization->id];
        }

        $messages = ['staff_establishment_ids.required' => __('Cochez au moins une école.')];

        if ($this->organization instanceof Foundation) {
            $messages['staff_email.unique'] = __("Cet e-mail a déjà un compte. Si cette personne est dans votre organisation, utilisez « Ajouter à d'autres écoles » dans la liste des utilisateurs.");
        }

        $data = $this->validate([
            'staff_name' => ['required', 'string', 'max:255'],
            'staff_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'staff_role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'staff_establishment_ids' => ['required', 'array'],
            'staff_establishment_ids.*' => [Rule::in($establishmentIds)],
        ], $messages);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['staff_name'],
                'email' => $data['staff_email'],
                'password' => User::DEFAULT_PASSWORD,
                'must_change_password' => true,
            ]);

            $this->attachToEstablishments($user, $data['staff_establishment_ids'], $data['staff_role']);

            return $user;
        });

        $this->generatedPassword = User::DEFAULT_PASSWORD;
        $this->generatedPasswordFor = $user->email;
        $this->reset(['staff_name', 'staff_email', 'staff_establishment_ids']);
    }

    public function setPdfHeaderStyle(int $establishmentId, string $style): void
    {
        $this->authorize('manageOrganization', $this->organization);

        abort_unless(in_array($establishmentId, $this->organizationEstablishmentIds(), true), 404);

        $headerStyle = PdfHeaderStyle::tryFrom($style);

        abort_if($headerStyle === null, 422);

        Establishment::findOrFail($establishmentId)->update(['pdf_header_style' => $headerStyle]);

        if ($this->organization instanceof Establishment) {
            $this->organization->refresh();
        }
    }

    public function startAddingToEstablishments(int $pivotId): void
    {
        $this->authorize('manageOrganization', $this->organization);

        $pivot = $this->findAddablePivot($pivotId);

        $this->resetValidation();
        $this->addingPivotId = $pivot->id;
        $this->add_role = $pivot->role;
        $this->add_establishment_ids = [];
    }

    /**
     * Ajoute une personne de l'organisation à d'autres écoles. Elle est
     * résolue depuis une ligne de la liste, jamais depuis un e-mail saisi :
     * un compte extérieur à l'organisation reste inaccessible.
     */
    public function saveAddToEstablishments(): void
    {
        $this->authorize('manageOrganization', $this->organization);

        $pivot = $this->findAddablePivot((int) $this->addingPivotId);

        $data = $this->validate([
            'add_role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'add_establishment_ids' => ['required', 'array'],
            'add_establishment_ids.*' => [Rule::in($this->availableEstablishmentIdsFor($pivot->user_id))],
        ], [
            'add_establishment_ids.required' => __('Cochez au moins une école.'),
        ]);

        DB::transaction(fn () => $this->attachToEstablishments(
            User::findOrFail($pivot->user_id),
            $data['add_establishment_ids'],
            $data['add_role'],
        ));

        $this->cancelAddToEstablishments();
    }

    public function cancelAddToEstablishments(): void
    {
        $this->resetValidation();
        $this->reset(['addingPivotId', 'add_role', 'add_establishment_ids']);
    }

    /**
     * @param  array<int, int|string>  $establishmentIds
     */
    private function attachToEstablishments(User $user, array $establishmentIds, string $role): void
    {
        foreach (array_unique(array_map('intval', $establishmentIds)) as $establishmentId) {
            EstablishmentUserPivot::create([
                'establishment_id' => $establishmentId,
                'user_id' => $user->id,
                'role' => $role,
                'is_active' => true,
            ]);

            if ($role === 'enseignant') {
                Teacher::create([
                    'establishment_id' => $establishmentId,
                    'user_id' => $user->id,
                    'name' => $user->name,
                ]);
            }
        }
    }

    private function findAddablePivot(int $pivotId): EstablishmentUserPivot
    {
        abort_unless($this->organization instanceof Foundation, 404);

        $pivot = $this->findStaffPivot($pivotId);

        abort_unless(in_array($pivot->role, self::ASSIGNABLE_ROLES, true), 403);

        return $pivot;
    }

    /**
     * Écoles de l'organisation où la personne n'a encore aucune ligne, active
     * ou non : roleFor() ne lit qu'un rôle par école.
     *
     * @return list<int>
     */
    private function availableEstablishmentIdsFor(int $userId): array
    {
        $taken = EstablishmentUserPivot::where('user_id', $userId)->pluck('establishment_id')->all();

        return array_values(array_diff($this->organizationEstablishmentIds(), $taken));
    }

    public function createEstablishment(): void
    {
        abort_unless($this->organization instanceof Foundation, 404);

        $this->authorize('manageOrganization', $this->organization);

        $data = $this->validate([
            'new_establishment_name' => ['required', 'string', 'max:255'],
            'new_establishment_type' => ['required', Rule::enum(EstablishmentType::class)],
            'new_establishment_address' => ['nullable', 'string', 'max:255'],
            'new_establishment_phone' => ['nullable', 'string', 'max:50'],
            'new_establishment_inspection_id' => [
                Rule::requiredIf($this->new_establishment_type === EstablishmentType::PrescolairePrimaire->value),
                'nullable', 'integer', 'exists:inspections,id',
            ],
            'new_establishment_direction_id' => [
                Rule::requiredIf($this->new_establishment_type === EstablishmentType::Secondaire->value),
                'nullable', 'integer', 'exists:directions,id',
            ],
            'new_establishment_opening_code' => ['nullable', 'string', 'max:100'],
            'new_establishment_dsps_code' => ['nullable', 'string', 'max:100'],
            'new_establishment_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'new_establishment_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'new_establishment_email' => ['nullable', 'email', 'max:255'],
            'new_establishment_is_arabe' => ['boolean'],
            'new_establishment_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
        ]);

        foreach (['new_establishment_inspection_id', 'new_establishment_direction_id', 'new_establishment_opening_code', 'new_establishment_dsps_code', 'new_establishment_latitude', 'new_establishment_longitude', 'new_establishment_email'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        // Inspection (primaire) et direction (secondaire) sont exclusives —
        // voir App\Livewire\Establishments\Index::save() pour la même règle.
        if ($data['new_establishment_type'] === EstablishmentType::PrescolairePrimaire->value) {
            $data['new_establishment_direction_id'] = null;
        } else {
            $data['new_establishment_inspection_id'] = null;
        }

        $logoPath = $this->new_establishment_logo?->store('establishments-logos', 'public');

        Establishment::create([
            'foundation_id' => $this->organization->id,
            'name' => $data['new_establishment_name'],
            'slug' => UniqueSlug::for(Establishment::class, $data['new_establishment_name']),
            'type' => $data['new_establishment_type'],
            'address' => $data['new_establishment_address'],
            'phone' => $data['new_establishment_phone'],
            'inspection_id' => $data['new_establishment_inspection_id'],
            'direction_id' => $data['new_establishment_direction_id'],
            'opening_code' => $data['new_establishment_opening_code'],
            'dsps_code' => $data['new_establishment_dsps_code'],
            'latitude' => $data['new_establishment_latitude'],
            'longitude' => $data['new_establishment_longitude'],
            'email' => $data['new_establishment_email'],
            'is_arabe' => $data['new_establishment_is_arabe'],
            'logo_path' => $logoPath,
        ]);

        $this->reset([
            'new_establishment_name', 'new_establishment_type', 'new_establishment_address', 'new_establishment_phone',
            'new_establishment_inspection_id', 'new_establishment_direction_id', 'new_establishment_opening_code', 'new_establishment_dsps_code',
            'new_establishment_latitude', 'new_establishment_longitude', 'new_establishment_email',
            'new_establishment_is_arabe', 'new_establishment_logo',
        ]);
    }

    public function activate(int $pivotId): void
    {
        $pivot = $this->findStaffPivot($pivotId);
        $this->authorize('update', $pivot);

        $pivot->update(['is_active' => true]);
    }

    public function deactivate(int $pivotId): void
    {
        $pivot = $this->findStaffPivot($pivotId);
        $this->authorize('update', $pivot);
        abort_if($pivot->user_id === Auth::id(), 422, __('Vous ne pouvez pas vous désactiver vous-même.'));

        $pivot->update(['is_active' => false]);
    }

    public function delete(int $pivotId): void
    {
        $pivot = $this->findStaffPivot($pivotId);
        $this->authorize('delete', $pivot);
        abort_if($pivot->user_id === Auth::id(), 422, __('Vous ne pouvez pas vous supprimer vous-même.'));

        $pivot->delete();
    }

    public function activateFondateur(int $pivotId): void
    {
        $this->authorize('manageOrganization', $this->organization);
        abort_unless($this->organization instanceof Foundation, 404);

        FoundationUserPivot::where('foundation_id', $this->organization->id)
            ->findOrFail($pivotId)
            ->update(['is_active' => true]);
    }

    public function deactivateFondateur(int $pivotId): void
    {
        $this->authorize('manageOrganization', $this->organization);
        abort_unless($this->organization instanceof Foundation, 404);

        $pivot = FoundationUserPivot::where('foundation_id', $this->organization->id)->findOrFail($pivotId);
        abort_if($pivot->user_id === Auth::id(), 422, __('Vous ne pouvez pas vous désactiver vous-même.'));

        $pivot->update(['is_active' => false]);
    }

    public function nominateLocalAdmin(int $pivotId): void
    {
        $this->authorize('manageOrganization', $this->organization);

        $target = $this->findStaffPivot($pivotId);

        DB::transaction(function () use ($target): void {
            EstablishmentUserPivot::where('establishment_id', $target->establishment_id)
                ->where('is_local_admin', true)
                ->update(['is_local_admin' => null]);

            $target->update(['is_local_admin' => true]);
        });
    }

    public function dismissLocalAdmin(int $establishmentId): void
    {
        $this->authorize('manageOrganization', $this->organization);
        abort_unless(in_array($establishmentId, $this->organizationEstablishmentIds(), true), 403);

        EstablishmentUserPivot::where('establishment_id', $establishmentId)
            ->where('is_local_admin', true)
            ->update(['is_local_admin' => null]);
    }

    public function cedeGeneralAdmin(int $targetPivotId): void
    {
        $this->authorize('manageOrganization', $this->organization);

        DB::transaction(function () use ($targetPivotId): void {
            if ($this->organization instanceof Foundation) {
                $target = FoundationUserPivot::where('foundation_id', $this->organization->id)
                    ->where('role', 'fondateur')
                    ->where('is_active', true)
                    ->findOrFail($targetPivotId);

                FoundationUserPivot::where('foundation_id', $this->organization->id)
                    ->where('is_general_admin', true)
                    ->update(['is_general_admin' => null]);
            } else {
                $target = EstablishmentUserPivot::where('establishment_id', $this->organization->id)
                    ->whereIn('role', ['fondateur', 'directeur', 'gestionnaire'])
                    ->where('is_active', true)
                    ->findOrFail($targetPivotId);

                EstablishmentUserPivot::where('establishment_id', $this->organization->id)
                    ->where('is_general_admin', true)
                    ->update(['is_general_admin' => null]);
            }

            $target->update(['is_general_admin' => true]);
        });
    }

    public function reclaim(): void
    {
        $this->authorize('reclaimGeneralAdmin', $this->organization);

        /** @var User $user */
        $user = Auth::user();

        DB::transaction(function () use ($user): void {
            if ($this->organization instanceof Foundation) {
                FoundationUserPivot::where('foundation_id', $this->organization->id)
                    ->where('is_general_admin', true)
                    ->update(['is_general_admin' => null]);

                FoundationUserPivot::where('foundation_id', $this->organization->id)
                    ->where('user_id', $user->id)
                    ->update(['is_general_admin' => true]);
            } else {
                EstablishmentUserPivot::where('establishment_id', $this->organization->id)
                    ->where('is_general_admin', true)
                    ->update(['is_general_admin' => null]);

                EstablishmentUserPivot::where('establishment_id', $this->organization->id)
                    ->where('user_id', $user->id)
                    ->update(['is_general_admin' => true]);
            }
        });
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();
        $isGeneralAdmin = $user->isGeneralAdminOf($this->organization);
        $establishmentIds = $this->organizationEstablishmentIds();
        $establishments = $this->organization instanceof Foundation
            ? $this->organization->establishments()->orderBy('name')->get()
            : collect([$this->organization]);

        return view('livewire.staff.manage-organization', [
            'isGeneralAdmin' => $isGeneralAdmin,
            'establishments' => $establishments,
            'assignableRoleCodes' => self::ASSIGNABLE_ROLES,
            'pdfHeaderStyles' => PdfHeaderStyle::cases(),
            'staffMembers' => $isGeneralAdmin
                ? EstablishmentUserPivot::whereIn('establishment_id', $establishmentIds)->with(['user', 'establishment'])->orderBy('role')->get()
                : collect(),
            'fondateurs' => $isGeneralAdmin && $this->organization instanceof Foundation
                ? FoundationUserPivot::where('foundation_id', $this->organization->id)->with('user')->get()
                : collect(),
            'eligibleGeneralAdminTargets' => $isGeneralAdmin ? $this->eligibleGeneralAdminTargets() : collect(),
            'establishmentTypes' => EstablishmentType::cases(),
            'inspections' => Inspection::orderBy('inspection_name')->get(),
            'directions' => Direction::orderBy('direction_name')->get(),
            'assignableRoles' => Role::whereIn('code', self::ASSIGNABLE_ROLES)
                ->get()
                ->sortBy(fn (Role $role): int => array_search($role->code, self::ASSIGNABLE_ROLES, true)),
        ])->title(__('Mon organisation'));
    }

    /**
     * Une ligne foundation_user peut survivre à la suppression (soft delete)
     * de la fondation visée (ex : fondation recréée sans que l'ancien lien
     * du fondateur soit nettoyé) — Foundation::findOrFail planterait alors
     * en 404 sur ce résidu. On ne retient donc que les fondations encore
     * existantes plutôt que la première ligne trouvée.
     */
    private function resolveOrganizationFor(User $user): Foundation|Establishment|null
    {
        $foundationIds = FoundationUserPivot::where('user_id', $user->id)
            ->where('role', 'fondateur')
            ->where('is_active', true)
            ->pluck('foundation_id');

        if ($foundationIds->isNotEmpty()) {
            $foundation = Foundation::whereIn('id', $foundationIds)->first();

            if ($foundation !== null) {
                return $foundation;
            }
        }

        $establishmentPivot = EstablishmentUserPivot::where('user_id', $user->id)
            ->where('role', 'fondateur')
            ->where('is_active', true)
            ->first();

        if ($establishmentPivot !== null) {
            return Establishment::findOrFail($establishmentPivot->establishment_id);
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function organizationEstablishmentIds(): array
    {
        return $this->organization instanceof Foundation
            ? $this->organization->establishments()->pluck('id')->all()
            : [$this->organization->id];
    }

    private function findStaffPivot(int $pivotId): EstablishmentUserPivot
    {
        return EstablishmentUserPivot::whereIn('establishment_id', $this->organizationEstablishmentIds())
            ->findOrFail($pivotId);
    }

    /**
     * Titulaires admissibles pour une cession du GENERAL_ADMIN — n'inclut
     * pas le titulaire actuel lui-même (rien à céder à soi-même).
     */
    private function eligibleGeneralAdminTargets(): Collection
    {
        if ($this->organization instanceof Foundation) {
            return FoundationUserPivot::where('foundation_id', $this->organization->id)
                ->where('role', 'fondateur')
                ->where('is_active', true)
                ->whereNull('is_general_admin')
                ->with('user')
                ->get();
        }

        return EstablishmentUserPivot::where('establishment_id', $this->organization->id)
            ->whereIn('role', ['fondateur', 'directeur', 'gestionnaire'])
            ->where('is_active', true)
            ->where('is_general_admin', '!=', true)
            ->with('user')
            ->get();
    }
}
