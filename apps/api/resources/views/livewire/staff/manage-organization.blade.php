<div>
    <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900">&larr; Retour au tableau de bord</a>

    <h1 class="mt-2 text-2xl font-semibold text-slate-900">
        {{ $organization instanceof \App\Domain\Establishments\Models\Foundation ? $organization->name : $organization->name }}
        — Organisation
    </h1>

    @unless ($isGeneralAdmin)
        <div class="mt-4 rounded-md border border-slate-200 bg-white p-4 text-sm text-slate-600">
            Vous n'êtes pas administrateur général de cette organisation actuellement.
            <button wire:click="reclaim" wire:confirm="Réclamer le statut d'administrateur général ?" class="ml-2 font-medium text-indigo-600 hover:text-indigo-800">
                Réclamer le statut
            </button>
        </div>
    @else
        @if ($generatedPassword)
            <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Compte créé pour <span class="font-medium">{{ $generatedPasswordFor }}</span>. Mot de passe temporaire (affiché une seule fois) :
                <span class="font-mono font-semibold">{{ $generatedPassword }}</span>
            </div>
        @endif

        @if ($organization instanceof \App\Domain\Establishments\Models\Foundation)
            <section class="mt-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Établissements du groupe</h2>
                <div class="mt-2 overflow-hidden rounded-md border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($establishments as $groupEstablishment)
                                <tr wire:key="group-establishment-{{ $groupEstablishment->id }}">
                                    <td class="px-4 py-2 text-slate-900">{{ $groupEstablishment->name }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ $groupEstablishment->type?->label() ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-6 text-center text-slate-500">Aucun établissement.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form wire:submit="createEstablishment" class="mt-3 space-y-2 rounded-md border border-slate-200 bg-white p-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Créer un établissement</h3>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Nom</label>
                        <input type="text" wire:model="new_establishment_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('new_establishment_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Type</label>
                        <select wire:model="new_establishment_type" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            <option value="">—</option>
                            @foreach ($establishmentTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('new_establishment_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Adresse</label>
                        <input type="text" wire:model="new_establishment_address" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('new_establishment_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Téléphone</label>
                        <input type="text" wire:model="new_establishment_phone" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('new_establishment_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">E-mail</label>
                        <input type="email" wire:model="new_establishment_email" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('new_establishment_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Inspection</label>
                        <select wire:model="new_establishment_inspection_code" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            <option value="">—</option>
                            @foreach ($inspections as $inspection)
                                <option value="{{ $inspection->code }}">{{ $inspection->libelle }}</option>
                            @endforeach
                        </select>
                        @error('new_establishment_inspection_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Code d'ouverture</label>
                        <input type="text" wire:model="new_establishment_opening_code" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('new_establishment_opening_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Code DSPS</label>
                        <input type="text" wire:model="new_establishment_dsps_code" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('new_establishment_dsps_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Latitude</label>
                        <input type="text" wire:model="new_establishment_latitude" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('new_establishment_latitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Longitude</label>
                        <input type="text" wire:model="new_establishment_longitude" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('new_establishment_longitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Logo</label>
                        <input type="file" wire:model="new_establishment_logo" class="mt-1 block w-full text-sm">
                        @error('new_establishment_logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="new_establishment_is_arabe" class="rounded border-slate-300">
                        École arabe
                    </label>
                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                        Créer l'établissement
                    </button>
                </form>
            </section>

            <section class="mt-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Fondateurs</h2>
                <div class="mt-2 overflow-hidden rounded-md border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($fondateurs as $fondateur)
                                <tr wire:key="fondateur-{{ $fondateur->id }}">
                                    <td class="px-4 py-2 text-slate-900">
                                        {{ $fondateur->user->name }}
                                        <span class="block text-xs text-slate-500">{{ $fondateur->user->email }}</span>
                                        @if ($fondateur->is_general_admin)
                                            <span class="ml-1 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">Administrateur général</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $fondateur->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $fondateur->is_active ? 'Actif' : 'En attente / Inactif' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        @if ($fondateur->user_id !== auth()->id())
                                            @if ($fondateur->is_active)
                                                <button wire:click="deactivateFondateur({{ $fondateur->id }})" wire:confirm="Désactiver ce fondateur ?" class="text-slate-500 hover:text-slate-700">Désactiver</button>
                                            @else
                                                <button wire:click="activateFondateur({{ $fondateur->id }})" class="text-indigo-600 hover:text-indigo-800">Activer</button>
                                            @endif
                                        @else
                                            <span class="text-xs text-slate-400">Vous</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-6 text-center text-slate-500">Aucun fondateur.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="mt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Utilisateurs</h2>
            <div class="mt-2 overflow-hidden rounded-md border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Nom</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Établissement</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Rôle</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Statut</th>
                            <th class="px-4 py-2 text-right font-medium text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($staffMembers as $member)
                            <tr wire:key="member-{{ $member->id }}">
                                <td class="px-4 py-2 text-slate-900">
                                    {{ $member->user->name }}
                                    <span class="block text-xs text-slate-500">{{ $member->user->email }}</span>
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $member->establishment->name }}</td>
                                <td class="px-4 py-2 text-slate-600">
                                    {{ ucfirst($member->role) }}
                                    @if ($member->is_local_admin)
                                        <span class="ml-1 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">Admin local</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $member->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $member->is_active ? 'Actif' : 'En attente / Inactif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    @if ($member->user_id !== auth()->id())
                                        @if ($member->is_active)
                                            <button wire:click="deactivate({{ $member->id }})" wire:confirm="Désactiver ce compte ?" class="text-slate-500 hover:text-slate-700">Désactiver</button>
                                        @else
                                            <button wire:click="activate({{ $member->id }})" class="text-indigo-600 hover:text-indigo-800">Activer</button>
                                        @endif

                                        @if ($member->is_local_admin)
                                            <button wire:click="dismissLocalAdmin({{ $member->establishment_id }})" wire:confirm="Démettre cet administrateur local ?" class="text-slate-500 hover:text-slate-700">Démettre admin local</button>
                                        @else
                                            <button wire:click="nominateLocalAdmin({{ $member->id }})" class="text-indigo-600 hover:text-indigo-800">Nommer admin local</button>
                                        @endif

                                        <button wire:click="delete({{ $member->id }})" wire:confirm="Supprimer ce compte ? Cette action est irréversible." class="text-red-500 hover:text-red-700">Supprimer</button>
                                    @else
                                        <span class="text-xs text-slate-400">Vous</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucun utilisateur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <form wire:submit="create" class="mt-6 max-w-md space-y-3 rounded-md border border-slate-200 bg-white p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Créer un compte</h2>

            @if ($establishments->count() > 1)
                <div>
                    <label class="block text-xs font-medium text-slate-700">Établissement</label>
                    <select wire:model="staff_establishment_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        <option value="">Sélectionner…</option>
                        @foreach ($establishments as $establishment)
                            <option value="{{ $establishment->id }}">{{ $establishment->name }}</option>
                        @endforeach
                    </select>
                    @error('staff_establishment_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-slate-700">Nom</label>
                <input type="text" wire:model="staff_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('staff_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700">E-mail</label>
                <input type="email" wire:model="staff_email" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('staff_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700">Rôle</label>
                <select wire:model="staff_role" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="enseignant">Enseignant</option>
                    <option value="caissier">Caissier</option>
                    <option value="educateur">Éducateur</option>
                </select>
            </div>
            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Créer le compte
            </button>
        </form>

        @if ($eligibleGeneralAdminTargets->isNotEmpty())
            <section class="mt-6 max-w-md rounded-md border border-slate-200 bg-white p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Céder l'administration générale</h2>
                <div class="mt-2 space-y-2">
                    @foreach ($eligibleGeneralAdminTargets as $target)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-700">{{ $target->user->name }} ({{ $target->role }})</span>
                            <button wire:click="cedeGeneralAdmin({{ $target->id }})" wire:confirm="Céder l'administration générale à {{ $target->user->name }} ?" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                Céder
                            </button>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endunless
</div>
