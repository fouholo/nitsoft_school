<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Services;

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Domain\Establishments\Models\SaasAdmin;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Examen, par un administrateur SaaS, des auto-inscriptions du personnel
 * (Staff\Register) que personne d'autre ne peut activer : celles qui visent
 * un établissement ou une fondation sans administrateur actif. Quand
 * l'organisation a déjà un administrateur, c'est lui qui active depuis
 * Staff\ManageOrganization / Staff\Index.
 */
class StaffRegistrationReviewer
{
    /** Rôles ouverts à l'auto-inscription. */
    private const SELF_REGISTRABLE_ROLES = ['fondateur', 'directeur', 'gestionnaire'];

    /**
     * @return Collection<int, EstablishmentUserPivot>
     */
    public function pendingEstablishmentMembers(): Collection
    {
        return EstablishmentUserPivot::query()
            ->where('is_active', false)
            ->whereIn('role', self::SELF_REGISTRABLE_ROLES)
            ->with(['user', 'establishment'])
            ->oldest()
            ->get()
            ->filter(fn (EstablishmentUserPivot $pivot): bool => $pivot->user !== null
                && $pivot->establishment !== null
                && ! $this->establishmentHasApprover($pivot->establishment))
            ->values();
    }

    /**
     * @return Collection<int, FoundationUserPivot>
     */
    public function pendingFounders(): Collection
    {
        return FoundationUserPivot::query()
            ->where('is_active', false)
            ->where('role', 'fondateur')
            ->with(['user', 'foundation'])
            ->oldest()
            ->get()
            ->filter(fn (FoundationUserPivot $pivot): bool => $pivot->user !== null
                && $pivot->foundation !== null
                && ! $this->foundationHasApprover($pivot->foundation_id))
            ->values();
    }

    public function pendingCount(): int
    {
        return $this->pendingEstablishmentMembers()->count() + $this->pendingFounders()->count();
    }

    /**
     * Active le membre et lui confie le pouvoir d'administration
     * correspondant à son rôle s'il est encore vacant (administrateur général
     * pour un fondateur, administrateur local sinon).
     */
    public function approveEstablishmentMember(EstablishmentUserPivot $pivot, User $author): void
    {
        DB::transaction(function () use ($pivot): void {
            $flag = $pivot->role === 'fondateur' ? 'is_general_admin' : 'is_local_admin';

            $slotTaken = EstablishmentUserPivot::where('establishment_id', $pivot->establishment_id)
                ->where($flag, true)
                ->lockForUpdate()
                ->exists();

            $pivot->update(['is_active' => true, $flag => $slotTaken ? null : true]);
        });

        Log::info('staff_registration.approved', [
            'user_id' => $pivot->user_id,
            'establishment_id' => $pivot->establishment_id,
            'role' => $pivot->role,
            'author' => $author->email,
        ]);
    }

    public function approveFounder(FoundationUserPivot $pivot, User $author): void
    {
        DB::transaction(function () use ($pivot): void {
            $slotTaken = FoundationUserPivot::where('foundation_id', $pivot->foundation_id)
                ->where('is_general_admin', true)
                ->lockForUpdate()
                ->exists();

            $pivot->update(['is_active' => true, 'is_general_admin' => $slotTaken ? null : true]);
        });

        Log::info('staff_registration.approved', [
            'user_id' => $pivot->user_id,
            'foundation_id' => $pivot->foundation_id,
            'role' => $pivot->role,
            'author' => $author->email,
        ]);
    }

    /**
     * Supprime le rattachement, et le compte lui-même s'il ne sert à rien
     * d'autre (aucun autre rattachement, ni profil parent, ni admin SaaS).
     */
    public function reject(EstablishmentUserPivot|FoundationUserPivot $pivot, User $author): void
    {
        $userId = (int) $pivot->user_id;

        DB::transaction(function () use ($pivot, $userId): void {
            $pivot->delete();

            if ($this->isOrphanAccount($userId)) {
                User::whereKey($userId)->delete();
            }
        });

        Log::info('staff_registration.rejected', [
            'user_id' => $userId,
            'role' => $pivot->role,
            'author' => $author->email,
        ]);
    }

    private function establishmentHasApprover(Establishment $establishment): bool
    {
        $direct = EstablishmentUserPivot::where('establishment_id', $establishment->id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('is_local_admin', true)->orWhere('is_general_admin', true))
            ->exists();

        return $direct
            || ($establishment->foundation_id !== null && $this->foundationHasApprover($establishment->foundation_id));
    }

    private function foundationHasApprover(int $foundationId): bool
    {
        return FoundationUserPivot::where('foundation_id', $foundationId)
            ->where('is_active', true)
            ->where('is_general_admin', true)
            ->exists();
    }

    private function isOrphanAccount(int $userId): bool
    {
        return ! EstablishmentUserPivot::where('user_id', $userId)->exists()
            && ! FoundationUserPivot::where('user_id', $userId)->exists()
            && ! Guardian::where('user_id', $userId)->exists()
            && ! SaasAdmin::where('user_id', $userId)->exists();
    }
}
