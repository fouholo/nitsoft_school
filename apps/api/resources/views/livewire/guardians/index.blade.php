<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Tuteurs</h1>

        @can('create', \App\Domain\Enrollment\Models\Guardian::class)
            <button type="button" wire:click="create" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                Nouveau tuteur
            </button>
        @endcan
    </div>

    <div class="mt-4">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Rechercher un tuteur..."
            class="block w-full max-w-sm rounded-md border-slate-300 text-sm"
        >
    </div>

    @if ($generatedPassword)
        <div class="mt-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Compte portail créé pour <strong>{{ $generatedPasswordFor }}</strong>. Mot de passe temporaire
            (à communiquer au tuteur, ne sera plus affiché) :
            <code class="rounded bg-amber-100 px-1.5 py-0.5 font-mono">{{ $generatedPassword }}</code>
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">Prénom</label>
                <input type="text" wire:model="first_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Nom</label>
                <input type="text" wire:model="last_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Lien de parenté</label>
                <select wire:model="relationship" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    <option value="pere">Père</option>
                    <option value="mere">Mère</option>
                    <option value="tuteur">Tuteur</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Téléphone</label>
                <input type="text" wire:model="phone" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 sm:col-span-3">
                <input type="checkbox" wire:model="createPortalAccount" class="rounded border-slate-300">
                Créer un compte d'accès au portail parents (nécessite une adresse e-mail)
            </label>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Nom</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Lien</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Téléphone</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">E-mail</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Portail</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($guardians as $guardian)
                    <tr wire:key="guardian-{{ $guardian->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $guardian->last_name }} {{ $guardian->first_name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $guardian->relationship }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $guardian->phone }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $guardian->email }}</td>
                        <td class="px-4 py-2">
                            @if ($guardian->user_id)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Actif</span>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $guardian)
                                <button wire:click="edit({{ $guardian->id }})" class="text-slate-500 hover:text-slate-900">Modifier</button>
                            @endcan
                            @can('delete', $guardian)
                                <button
                                    wire:click="delete({{ $guardian->id }})"
                                    wire:confirm="Supprimer ce tuteur ?"
                                    class="ml-3 text-red-500 hover:text-red-700"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">Aucun tuteur.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $guardians->links() }}
        </div>
    </div>
</div>
