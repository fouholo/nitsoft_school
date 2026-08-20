<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Tuteurs</h1>

        @can('create', \App\Domain\Enrollment\Models\Guardian::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                Nouveau tuteur
            </button>
        @endcan
    </div>

    <div class="mt-4">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Rechercher un tuteur..."
            class="block w-full max-w-sm rounded-lg border-stone-300 text-sm"
        >
    </div>

    @if ($unlinkedGuardianNotice)
        <div class="mt-4 flex items-start justify-between gap-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
            <p><strong>{{ $unlinkedGuardianNotice }}</strong> a été créé. Il n'apparaîtra dans cette liste qu'une fois lié à un élève — depuis la fiche de l'élève, utilisez « Ajouter un tuteur » pour le rechercher et le rattacher.</p>
            <button type="button" wire:click="$set('unlinkedGuardianNotice', null)" class="inline-flex min-h-11 shrink-0 items-center text-orange-700 hover:text-orange-900">
                Fermer
            </button>
        </div>
    @endif

    @if ($generatedPassword)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-100 px-4 py-3 text-sm text-amber-900" role="alert" aria-live="assertive">
            <p class="font-semibold">Compte portail créé pour {{ $generatedPasswordFor }}</p>
            <p class="mt-1">Mot de passe par défaut à communiquer au tuteur — invitez-le à le changer dès sa première connexion (menu de son profil) :</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <code class="rounded bg-amber-200 px-2 py-1 font-mono text-base">{{ $generatedPassword }}</code>
                <button
                    type="button"
                    x-data
                    x-on:click="navigator.clipboard.writeText(@js($generatedPassword))"
                    class="inline-flex min-h-11 items-center rounded-lg border border-amber-300 bg-white px-3 text-xs font-medium text-amber-800 hover:bg-amber-50"
                >
                    Copier
                </button>
            </div>
            <button type="button" wire:click="dismissGeneratedPassword" class="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-amber-800 underline">
                Fermer
            </button>
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-stone-700">Prénom</label>
                <input type="text" wire:model="first_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Nom</label>
                <input type="text" wire:model="last_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Téléphone</label>
                <input type="text" wire:model="phone" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">E-mail</label>
                <input type="email" wire:model="email" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-600 sm:col-span-3">
                <input type="checkbox" wire:model="createPortalAccount" class="rounded border-stone-300">
                Créer un compte d'accès au portail parents (nécessite une adresse e-mail)
            </label>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Nom</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Téléphone</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">E-mail</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Portail</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($guardians as $guardian)
                        <tr wire:key="guardian-{{ $guardian->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $guardian->last_name }} {{ $guardian->first_name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $guardian->phone }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $guardian->email }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($guardian->user_id)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Actif</span>
                                @else
                                    <span class="text-xs text-stone-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                @can('update', $guardian)
                                    <button wire:click="edit({{ $guardian->id }})" class="inline-flex min-h-11 items-center text-stone-500 hover:text-stone-900">Modifier</button>
                                @endcan
                                @can('delete', $guardian)
                                    <button
                                        wire:click="delete({{ $guardian->id }})"
                                        wire:confirm="Supprimer ce tuteur ?"
                                        class="ml-3 inline-flex min-h-11 items-center text-red-600 hover:text-red-700"
                                    >
                                        Supprimer
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-stone-500">Aucun tuteur.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-stone-200 px-4 py-3">
            {{ $guardians->links() }}
        </div>
    </div>
</div>
