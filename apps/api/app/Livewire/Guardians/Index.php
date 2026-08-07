<?php

declare(strict_types=1);

namespace App\Livewire\Guardians;

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Tuteurs')]
class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $email = '';

    public bool $createPortalAccount = false;

    public ?string $generatedPassword = null;

    public ?string $generatedPasswordFor = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Guardian::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Guardian::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $guardianId): void
    {
        $guardian = Guardian::findOrFail($guardianId);

        $this->authorize('update', $guardian);

        $this->editingId = $guardian->id;
        $this->first_name = $guardian->first_name;
        $this->last_name = $guardian->last_name;
        $this->phone = (string) $guardian->phone;
        $this->email = (string) $guardian->email;
        $this->createPortalAccount = false;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->generatedPassword = null;
        $this->generatedPasswordFor = null;

        $data = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        foreach (['phone', 'email'] as $optional) {
            $data[$optional] = $data[$optional] !== '' ? $data[$optional] : null;
        }

        if ($this->editingId) {
            $guardian = Guardian::findOrFail($this->editingId);
            $this->authorize('update', $guardian);
        } else {
            $this->authorize('create', Guardian::class);
            $guardian = new Guardian;
        }

        $guardian->fill($data);
        $guardian->save();

        if ($this->createPortalAccount && ! $guardian->user_id) {
            $this->createPortalAccountFor($guardian);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    protected function createPortalAccountFor(Guardian $guardian): void
    {
        if (! $guardian->email) {
            $this->addError('email', 'Une adresse e-mail est requise pour créer un compte portail.');

            return;
        }

        $password = Str::password(12);

        $user = User::create([
            'name' => trim("{$guardian->first_name} {$guardian->last_name}"),
            'email' => $guardian->email,
            'phone' => $guardian->phone,
            'password' => $password,
        ]);

        /** @var Establishment $establishment */
        $establishment = Establishment::findOrFail((int) app('currentEstablishmentId'));
        $establishment->users()->attach($user->id, ['role' => 'parent', 'is_active' => true]);

        $guardian->update(['user_id' => $user->id]);

        $this->generatedPassword = $password;
        $this->generatedPasswordFor = $guardian->email;
    }

    public function delete(int $guardianId): void
    {
        $guardian = Guardian::findOrFail($guardianId);

        $this->authorize('delete', $guardian);

        $guardian->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'first_name', 'last_name', 'phone', 'email', 'createPortalAccount']);
    }

    public function render()
    {
        $guardians = Guardian::query()
            ->whereHas('students', fn ($query) => $query
                ->wherePivot('establishment_id', app('currentEstablishmentId'))
                ->wherePivot('status', GuardianLinkStatus::Approved))
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('last_name')
            ->paginate(15);

        return view('livewire.guardians.index', [
            'guardians' => $guardians,
        ]);
    }
}
