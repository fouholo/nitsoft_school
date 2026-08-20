<?php

declare(strict_types=1);

namespace App\Livewire\SaasAdmins;

use App\Domain\Establishments\Enums\SaasAdminType;
use App\Domain\Establishments\Models\SaasAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Administrateurs SaaS')]
class Index extends Component
{
    public string $admin_name = '';

    public string $admin_email = '';

    public ?string $generatedPassword = null;

    public ?string $generatedPasswordFor = null;

    public function mount(): void
    {
        $this->authorize('viewAny', SaasAdmin::class);
    }

    public function create(): void
    {
        $this->authorize('create', SaasAdmin::class);

        $this->generatedPassword = null;
        $this->generatedPasswordFor = null;

        $data = $this->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = User::create([
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => User::DEFAULT_PASSWORD,
        ]);

        SaasAdmin::create([
            'user_id' => $user->id,
            'type' => SaasAdminType::Second,
            'is_active' => true,
        ]);

        $this->generatedPassword = User::DEFAULT_PASSWORD;
        $this->generatedPasswordFor = $user->email;
        $this->reset(['admin_name', 'admin_email']);
    }

    public function activate(int $saasAdminId): void
    {
        $saasAdmin = SaasAdmin::findOrFail($saasAdminId);
        $this->authorize('update', $saasAdmin);

        $saasAdmin->update(['is_active' => true]);
    }

    public function deactivate(int $saasAdminId): void
    {
        $saasAdmin = SaasAdmin::findOrFail($saasAdminId);
        $this->authorize('update', $saasAdmin);
        abort_if($saasAdmin->user_id === Auth::id(), 422, 'Vous ne pouvez pas vous désactiver vous-même.');

        $saasAdmin->update(['is_active' => false]);
    }

    public function delete(int $saasAdminId): void
    {
        $saasAdmin = SaasAdmin::findOrFail($saasAdminId);
        $this->authorize('delete', $saasAdmin);
        abort_if($saasAdmin->user_id === Auth::id(), 422, 'Vous ne pouvez pas vous supprimer vous-même.');

        $saasAdmin->delete();
    }

    public function render()
    {
        return view('livewire.saas-admins.index', [
            'admins' => SaasAdmin::with('user')->orderByDesc('type')->orderBy('created_at')->get(),
        ]);
    }
}
