<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Filtre "Écoles" du tableau de bord — monté par le parent Dashboard.php
 * uniquement pour un fondateur d'un groupe à 2+ établissements. Diffuse sa
 * sélection aux autres widgets via l'événement establishment-filter-changed
 * plutôt que via une propriété partagée : ce sont des composants Livewire
 * frères, sans lien de props direct entre eux.
 */
class EstablishmentFilter extends Component
{
    /**
     * @var list<int>
     */
    public array $selected = [];

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->selected = $user->founderGroupEstablishments()->pluck('id')->all();
    }

    public function updatedSelected(): void
    {
        $this->dispatch('establishment-filter-changed', establishmentIds: $this->selected);
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.dashboard.establishment-filter', [
            'groupEstablishments' => $user->founderGroupEstablishments(),
        ]);
    }
}
