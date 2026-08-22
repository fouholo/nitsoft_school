<div>
    <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-stone-500 hover:text-stone-900">&larr; {{ __('Retour au tableau de bord') }}</a>

    <h1 class="mt-2 text-2xl font-semibold text-stone-900">{{ __(':name — Utilisateurs', ['name' => $establishment->name]) }}</h1>

    @if ($generatedPassword)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            {{ __('Compte créé pour :name avec le mot de passe par défaut :', ['name' => $generatedPasswordFor]) }}
            <span class="font-mono font-semibold">{{ $generatedPassword }}</span> — {{ __('invitez la personne à le changer dès sa première connexion (menu de son profil).') }}
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Rôle') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                    <th class="px-4 py-2 text-end font-medium text-stone-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($staffMembers as $member)
                    <tr wire:key="staff-{{ $member->id }}">
                        <td class="px-4 py-2 text-stone-900">
                            <a href="{{ route('staff.show', [$establishment, $member]) }}" wire:navigate class="font-medium text-orange-700 hover:underline">
                                {{ $member->user->name }}
                            </a>
                            <span class="block text-xs text-stone-500">{{ $member->user->email }}</span>
                        </td>
                        <td class="px-4 py-2 text-stone-600">{{ \App\Models\User::roleLabel($member->role) }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $member->is_active ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $member->is_active ? __('Actif') : __('En attente / Inactif') }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-end">
                            @if ($member->user_id !== auth()->id())
                                @if ($member->is_active)
                                    <button wire:click="deactivate({{ $member->id }})" wire:confirm="{{ __('Désactiver ce compte ?') }}" class="text-stone-500 hover:text-stone-700">
                                        {{ __('Désactiver') }}
                                    </button>
                                @else
                                    <button wire:click="activate({{ $member->id }})" class="text-orange-700 hover:text-orange-900">
                                        {{ __('Activer') }}
                                    </button>
                                @endif
                            @else
                                <span class="text-xs text-stone-400">{{ __('Vous') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun utilisateur.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form wire:submit="create" class="mt-6 max-w-md space-y-3 rounded-lg border border-stone-200 bg-white p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Créer un compte') }}</h2>

        <div>
            <label class="block text-xs font-medium text-stone-700">{{ __('Nom') }}</label>
            <input type="text" wire:model="staff_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('staff_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-700">{{ __('E-mail') }}</label>
            <input type="email" wire:model="staff_email" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('staff_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-700">{{ __('Rôle') }}</label>
            <select wire:model="staff_role" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                <option value="gestionnaire">{{ __('Gestionnaire') }}</option>
                <option value="enseignant">{{ __('Enseignant') }}</option>
                <option value="caissier">{{ __('Caissier') }}</option>
                <option value="educateur">{{ __('Éducateur') }}</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
            {{ __('Créer le compte') }}
        </button>
    </form>
</div>
