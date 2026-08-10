<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mon établissement')]
class Index extends Component
{
    public Establishment $establishment;

    public string $staff_name = '';

    public string $staff_email = '';

    public string $staff_role = 'enseignant';

    public ?string $generatedPassword = null;

    public ?string $generatedPasswordFor = null;

    public function mount(Establishment $establishment): void
    {
        $this->authorize('manageStaff', $establishment);

        $this->establishment = $establishment;
    }

    public function create(): void
    {
        $this->authorize('manageStaff', $this->establishment);

        $this->generatedPassword = null;
        $this->generatedPasswordFor = null;

        $data = $this->validate([
            'staff_name' => ['required', 'string', 'max:255'],
            'staff_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'staff_role' => ['required', Rule::in(['enseignant', 'caissier', 'educateur'])],
        ]);

        $password = Str::password(12);

        $user = User::create([
            'name' => $data['staff_name'],
            'email' => $data['staff_email'],
            'password' => $password,
        ]);

        EstablishmentUserPivot::create([
            'establishment_id' => $this->establishment->id,
            'user_id' => $user->id,
            'role' => $data['staff_role'],
            'is_active' => true,
        ]);

        $this->generatedPassword = $password;
        $this->generatedPasswordFor = $user->email;
        $this->reset(['staff_name', 'staff_email']);
    }

    public function activate(int $pivotId): void
    {
        $pivot = EstablishmentUserPivot::where('establishment_id', $this->establishment->id)->findOrFail($pivotId);
        $this->authorize('update', $pivot);

        $pivot->update(['is_active' => true]);
    }

    public function deactivate(int $pivotId): void
    {
        $pivot = EstablishmentUserPivot::where('establishment_id', $this->establishment->id)->findOrFail($pivotId);
        $this->authorize('update', $pivot);
        abort_if($pivot->user_id === Auth::id(), 422, 'Vous ne pouvez pas vous désactiver vous-même.');

        $pivot->update(['is_active' => false]);
    }

    public function render()
    {
        return view('livewire.staff.index', [
            'staffMembers' => EstablishmentUserPivot::where('establishment_id', $this->establishment->id)
                ->with('user')
                ->orderBy('role')
                ->get(),
        ]);
    }
}
