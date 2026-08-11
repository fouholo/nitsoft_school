<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Établissements</h1>

        @can('create', \App\Domain\Establishments\Models\Establishment::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            >
                Nouvel établissement
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Nom</label>
                <input type="text" wire:model="name" placeholder="Groupe Scolaire Excellence" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Fondation</label>
                <select wire:model="foundation_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">Aucune (indépendant)</option>
                    @foreach ($foundations as $foundation)
                        <option value="{{ $foundation->id }}">{{ $foundation->name }}</option>
                    @endforeach
                </select>
                @error('foundation_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Type</label>
                <select wire:model="type" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Adresse</label>
                <input type="text" wire:model="address" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Téléphone</label>
                <input type="text" wire:model="phone" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Inspection</label>
                <select wire:model="inspection_code" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($inspections as $inspection)
                        <option value="{{ $inspection->code }}">{{ $inspection->libelle }}</option>
                    @endforeach
                </select>
                @error('inspection_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Code d'ouverture</label>
                <input type="text" wire:model="opening_code" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('opening_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Code DSPS</label>
                <input type="text" wire:model="dsps_code" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('dsps_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Latitude</label>
                <input type="text" wire:model="latitude" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('latitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Longitude</label>
                <input type="text" wire:model="longitude" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('longitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Logo</label>
                @if ($existingLogoPath)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingLogoPath) }}" alt="Logo actuel" class="mt-1 mb-2 h-12 w-12 rounded-md object-cover">
                @endif
                <input type="file" wire:model="logo" class="mt-1 block w-full text-sm">
                @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300">
                Actif
            </label>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" wire:model="is_arabe" class="rounded border-slate-300">
                École arabe
            </label>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
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
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Fondation</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Type</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Statut</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($establishments as $establishment)
                    <tr wire:key="establishment-{{ $establishment->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $establishment->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $establishment->foundation?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $establishment->type?->label() ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($establishment->is_active)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Actif</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactif</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $establishment)
                                <button wire:click="edit({{ $establishment->id }})" class="text-slate-500 hover:text-slate-900">Modifier</button>
                            @endcan
                            @can('delete', $establishment)
                                <button
                                    wire:click="delete({{ $establishment->id }})"
                                    wire:confirm="Supprimer cet établissement ?"
                                    class="ml-3 text-red-500 hover:text-red-700"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucun établissement.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
