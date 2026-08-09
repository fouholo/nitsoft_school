<?php

declare(strict_types=1);

namespace App\Livewire\Foundations;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Groupe scolaire')]
class Show extends Component
{
    public Foundation $foundation;

    public ?int $establishment_to_link = null;

    public string $founder_name = '';

    public string $founder_email = '';

    public ?string $generatedPassword = null;

    public ?string $generatedPasswordFor = null;

    public function mount(Foundation $foundation): void
    {
        $this->authorize('view', $foundation);

        $this->foundation = $foundation;
    }

    public function linkEstablishment(): void
    {
        $this->authorize('update', $this->foundation);

        $data = $this->validate([
            'establishment_to_link' => ['required', 'integer', 'exists:establishments,id'],
        ]);

        // Garde-fou minimal contre un GENERAL_ADMIN "orphelin" : un établissement
        // indépendant rattaché à une fondation ne doit plus porter son propre
        // is_general_admin (l'autorité bascule au niveau fondation) — sinon ce
        // flag resterait dormant et pourrait ressusciter au unlink.
        EstablishmentUserPivot::where('establishment_id', $data['establishment_to_link'])
            ->update(['is_general_admin' => null]);

        Establishment::whereKey($data['establishment_to_link'])
            ->whereNull('foundation_id')
            ->update(['foundation_id' => $this->foundation->id]);

        $this->reset('establishment_to_link');
    }

    public function unlinkEstablishment(int $establishmentId): void
    {
        $this->authorize('update', $this->foundation);

        Establishment::whereKey($establishmentId)
            ->where('foundation_id', $this->foundation->id)
            ->update(['foundation_id' => null]);
    }

    public function addFounder(): void
    {
        $this->authorize('update', $this->foundation);

        $this->generatedPassword = null;
        $this->generatedPasswordFor = null;

        $data = $this->validate([
            'founder_name' => ['required', 'string', 'max:255'],
            'founder_email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::where('email', $data['founder_email'])->first();

        if ($user === null) {
            $password = Str::password(12);

            $user = User::create([
                'name' => $data['founder_name'],
                'email' => $data['founder_email'],
                'password' => $password,
            ]);

            $this->generatedPassword = $password;
            $this->generatedPasswordFor = $user->email;
        }

        FoundationUserPivot::firstOrCreate(
            ['foundation_id' => $this->foundation->id, 'user_id' => $user->id, 'role' => 'fondateur'],
            ['is_active' => true]
        );

        $this->reset(['founder_name', 'founder_email']);
    }

    public function removeFounder(int $foundationUserId): void
    {
        $this->authorize('update', $this->foundation);

        FoundationUserPivot::whereKey($foundationUserId)
            ->where('foundation_id', $this->foundation->id)
            ->delete();
    }

    public function render()
    {
        return view('livewire.foundations.show', [
            'establishments' => $this->foundation->establishments()->orderBy('name')->get(),
            'availableEstablishments' => Establishment::whereNull('foundation_id')->orderBy('name')->get(),
            'founders' => FoundationUserPivot::where('foundation_id', $this->foundation->id)
                ->where('role', 'fondateur')
                ->with('user')
                ->get(),
        ]);
    }
}
