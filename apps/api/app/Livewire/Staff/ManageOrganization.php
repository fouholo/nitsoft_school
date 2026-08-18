<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Domain\Establishments\Models\Inspection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Mon organisation')]
class ManageOrganization extends Component
{
    use WithFileUploads;

    public Foundation|Establishment $organization;

    public string $staff_name = '';

    public string $staff_email = '';

    public string $staff_role = 'enseignant';

    public ?int $staff_establishment_id = null;

    public ?string $generatedPassword = null;

    public ?string $generatedPasswordFor = null;

    public string $new_establishment_name = '';

    public string $new_establishment_type = '';

    public string $new_establishment_address = '';

    public string $new_establishment_phone = '';

    public string $new_establishment_inspection_id = '';

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

        if ($organization instanceof Establishment) {
            $this->staff_establishment_id = $organization->id;
        }
    }

    public function create(): void
    {
        $this->authorize('manageOrganization', $this->organization);

        $this->generatedPassword = null;
        $this->generatedPasswordFor = null;

        $establishmentIds = $this->organizationEstablishmentIds();

        $data = $this->validate([
            'staff_name' => ['required', 'string', 'max:255'],
            'staff_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'staff_role' => ['required', Rule::in(['directeur', 'gestionnaire', 'enseignant', 'caissier', 'educateur'])],
            'staff_establishment_id' => ['required', Rule::in($establishmentIds)],
        ]);

        $password = Str::password(12);

        $user = User::create([
            'name' => $data['staff_name'],
            'email' => $data['staff_email'],
            'password' => $password,
        ]);

        EstablishmentUserPivot::create([
            'establishment_id' => $data['staff_establishment_id'],
            'user_id' => $user->id,
            'role' => $data['staff_role'],
            'is_active' => true,
        ]);

        $this->generatedPassword = $password;
        $this->generatedPasswordFor = $user->email;
        $this->reset(['staff_name', 'staff_email', 'staff_establishment_id']);
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
            'new_establishment_inspection_id' => ['nullable', 'integer', 'exists:inspections,id'],
            'new_establishment_opening_code' => ['nullable', 'string', 'max:100'],
            'new_establishment_dsps_code' => ['nullable', 'string', 'max:100'],
            'new_establishment_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'new_establishment_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'new_establishment_email' => ['nullable', 'email', 'max:255'],
            'new_establishment_is_arabe' => ['boolean'],
            'new_establishment_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
        ]);

        foreach (['new_establishment_inspection_id', 'new_establishment_opening_code', 'new_establishment_dsps_code', 'new_establishment_latitude', 'new_establishment_longitude', 'new_establishment_email'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        $logoPath = $this->new_establishment_logo?->store('establishments-logos', 'public');

        Establishment::create([
            'foundation_id' => $this->organization->id,
            'name' => $data['new_establishment_name'],
            'slug' => $this->uniqueSlugFor($data['new_establishment_name']),
            'type' => $data['new_establishment_type'],
            'address' => $data['new_establishment_address'],
            'phone' => $data['new_establishment_phone'],
            'inspection_id' => $data['new_establishment_inspection_id'],
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
            'new_establishment_inspection_id', 'new_establishment_opening_code', 'new_establishment_dsps_code',
            'new_establishment_latitude', 'new_establishment_longitude', 'new_establishment_email',
            'new_establishment_is_arabe', 'new_establishment_logo',
        ]);
    }

    private function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Establishment::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
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
        abort_if($pivot->user_id === Auth::id(), 422, 'Vous ne pouvez pas vous désactiver vous-même.');

        $pivot->update(['is_active' => false]);
    }

    public function delete(int $pivotId): void
    {
        $pivot = $this->findStaffPivot($pivotId);
        $this->authorize('delete', $pivot);
        abort_if($pivot->user_id === Auth::id(), 422, 'Vous ne pouvez pas vous supprimer vous-même.');

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
        abort_if($pivot->user_id === Auth::id(), 422, 'Vous ne pouvez pas vous désactiver vous-même.');

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

        return view('livewire.staff.manage-organization', [
            'isGeneralAdmin' => $isGeneralAdmin,
            'establishments' => $this->organization instanceof Foundation
                ? $this->organization->establishments()->orderBy('name')->get()
                : collect([$this->organization]),
            'staffMembers' => $isGeneralAdmin
                ? EstablishmentUserPivot::whereIn('establishment_id', $establishmentIds)->with(['user', 'establishment'])->orderBy('role')->get()
                : collect(),
            'fondateurs' => $isGeneralAdmin && $this->organization instanceof Foundation
                ? FoundationUserPivot::where('foundation_id', $this->organization->id)->with('user')->get()
                : collect(),
            'eligibleGeneralAdminTargets' => $isGeneralAdmin ? $this->eligibleGeneralAdminTargets() : collect(),
            'establishmentTypes' => EstablishmentType::cases(),
            'inspections' => Inspection::orderBy('inspection_name')->get(),
        ]);
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
