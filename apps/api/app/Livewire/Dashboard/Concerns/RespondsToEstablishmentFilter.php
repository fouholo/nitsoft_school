<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Partagé par les widgets du tableau de bord qui réagissent au filtre
 * "Écoles" (EstablishmentFilter) — voir
 * docs/superpowers/specs/2026-08-21-tableau-de-bord-fondateur-design.md.
 * Par défaut (pas de fondateur multi-écoles, ou avant tout événement),
 * chaque widget se limite à l'établissement couramment sélectionné.
 */
trait RespondsToEstablishmentFilter
{
    /**
     * @var list<int>
     */
    public array $establishmentIds = [];

    /**
     * Reflète le défaut de EstablishmentFilter (toutes les écoles cochées)
     * sans dépendre d'un événement initial — EstablishmentFilter ne
     * dispatch establishment-filter-changed qu'à l'interaction utilisateur
     * (updatedSelected()), jamais à son propre montage.
     */
    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $groupIds = $user->founderGroupEstablishments()->pluck('id');

        $this->establishmentIds = $groupIds->count() >= 2
            ? $groupIds->all()
            : (app()->bound('currentEstablishmentId') ? [(int) app('currentEstablishmentId')] : []);
    }

    /**
     * @param  list<int>  $establishmentIds
     */
    #[On('establishment-filter-changed')]
    public function onEstablishmentFilterChanged(array $establishmentIds): void
    {
        $this->establishmentIds = $establishmentIds;
    }

    /**
     * Ne fait jamais confiance à $establishmentIds tel quel : recalcule le
     * groupe réellement autorisé pour l'utilisateur connecté et
     * réintersecte — même garde que sur le Bilan financier, l'événement
     * client n'est jamais une source d'autorisation.
     *
     * @return list<int>
     */
    protected function authorizedEstablishmentIds(): array
    {
        /** @var User $user */
        $user = Auth::user();

        $groupIds = $user->founderGroupEstablishments()->pluck('id');

        if ($groupIds->count() < 2) {
            return app()->bound('currentEstablishmentId') ? [(int) app('currentEstablishmentId')] : [];
        }

        return $groupIds->intersect($this->establishmentIds)->values()->all();
    }
}
