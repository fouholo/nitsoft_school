<div>
    <h1 class="text-2xl font-semibold text-stone-900">Administrateurs SaaS</h1>

    @if ($generatedPassword)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Compte créé pour <span class="font-medium">{{ $generatedPasswordFor }}</span>. Mot de passe temporaire (affiché une seule fois) :
            <span class="font-mono font-semibold">{{ $generatedPassword }}</span>
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Nom</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Type</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Statut</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Créé le</th>
                    <th class="px-4 py-2 text-right font-medium text-stone-500">Actions</th>
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
                                {{ $admin->is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-stone-500">{{ $admin->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-right">
                            @if ($admin->user_id !== auth()->id())
                                @if ($admin->is_active)
                                    <button wire:click="deactivate({{ $admin->id }})" wire:confirm="Désactiver cet administrateur ?" class="text-stone-500 hover:text-stone-700">
                                        Désactiver
                                    </button>
                                @else
                                    <button wire:click="activate({{ $admin->id }})" class="text-orange-700 hover:text-orange-900">
                                        Activer
                                    </button>
                                @endif
                                <button wire:click="delete({{ $admin->id }})" wire:confirm="Supprimer cet administrateur ? Cette action est irréversible." class="ml-3 text-red-500 hover:text-red-700">
                                    Supprimer
                                </button>
                            @else
                                <span class="text-xs text-stone-400">Vous</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-stone-500">Aucun administrateur SaaS.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form wire:submit="create" class="mt-6 max-w-md space-y-3 rounded-lg border border-stone-200 bg-white p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Créer un administrateur secondaire</h2>

        <div>
            <label class="block text-xs font-medium text-stone-700">Nom</label>
            <input type="text" wire:model="admin_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('admin_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-700">E-mail</label>
            <input type="email" wire:model="admin_email" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error('admin_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
            Créer l'administrateur
        </button>
    </form>
</div>
