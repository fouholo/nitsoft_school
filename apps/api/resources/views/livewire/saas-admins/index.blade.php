<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Administrateurs SaaS') }}</h1>

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
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Type') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Créé le') }}</th>
                    <th class="px-4 py-2 text-end font-medium text-stone-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($admins as $admin)
                    <tr wire:key="admin-{{ $admin->id }}">
                        <td class="px-4 py-2 text-stone-900">
                            {{ $admin->user->name }}
                            <span class="block text-xs text-stone-500">{{ $admin->user->email }}</span>
                        </td>
                        <td class="px-4 py-2 text-stone-600">{{ $admin->type->label() }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $admin->is_active ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $admin->is_active ? __('Actif') : __('Inactif') }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-stone-500">{{ $admin->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-end">
                            @if ($admin->user_id !== auth()->id())
                                @if ($admin->is_active)
                                    <button wire:click="deactivate({{ $admin->id }})" wire:confirm="{{ __('Désactiver cet administrateur ?') }}" class="text-stone-500 hover:text-stone-700">
                                        {{ __('Désactiver') }}
                                    </button>
                                @else
                                    <button wire:click="activate({{ $admin->id }})" class="text-orange-700 hover:text-orange-900">
                                        {{ __('Activer') }}
                                    </button>
                                @endif
                                <button wire:click="delete({{ $admin->id }})" wire:confirm="{{ __('Supprimer cet administrateur ? Cette action est irréversible.') }}" class="ms-3 text-red-500 hover:text-red-700">
                                    {{ __('Supprimer') }}
                                </button>
                            @else
                                <span class="text-xs text-stone-400">{{ __('Vous') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun administrateur SaaS.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form wire:submit="create" class="mt-6 max-w-md space-y-3 rounded-lg border border-stone-200 bg-white p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Créer un administrateur secondaire') }}</h2>

        <div>
            <label class="block text-xs font-medium text-stone-700">{{ __('Nom') }}</label>
            <input type="text" wire:model="admin_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('admin_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-700">{{ __('E-mail') }}</label>
            <input type="email" wire:model="admin_email" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('admin_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
            {{ __("Créer l'administrateur") }}
        </button>
    </form>
</div>
