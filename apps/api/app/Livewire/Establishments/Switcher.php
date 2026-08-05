<?php

declare(strict_types=1);

namespace App\Livewire\Establishments;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Switcher extends Component
{
    public function switchTo(int $establishmentId): void
    {
        /** @var User $user */
        $user = Auth::user();

        abort_unless($user->belongsToEstablishment($establishmentId), 403);

        session(['current_establishment_id' => $establishmentId]);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.establishments.switcher', [
            'establishments' => $user->establishments()->wherePivot('is_active', true)->get(),
            'currentEstablishmentId' => session('current_establishment_id'),
        ]);
    }
}
