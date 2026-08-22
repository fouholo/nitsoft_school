<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Établissements') }}</h1>

        @can('create', \App\Domain\Establishments\Models\Establishment::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                {{ __('Nouvel établissement') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Nom') }}</label>
                <input type="text" wire:model="name" placeholder="{{ __('Groupe Scolaire Excellence') }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Fondation') }}</label>
                <select wire:model="foundation_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">{{ __('Aucune (indépendant)') }}</option>
                    @foreach ($foundations as $foundation)
                        <option value="{{ $foundation->id }}">{{ $foundation->name }}</option>
                    @endforeach
                </select>
                @error('foundation_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Type') }}</label>
                <select wire:model="type" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Adresse') }}</label>
                <input type="text" wire:model="address" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Téléphone') }}</label>
                <input type="text" wire:model="phone" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('E-mail') }}</label>
                <input type="email" wire:model="email" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Inspection') }}</label>
                <select wire:model="inspection_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($inspections as $inspection)
                        <option value="{{ $inspection->id }}">{{ $inspection->codeiep }} — {{ $inspection->inspection_name }}</option>
                    @endforeach
                </select>
                @error('inspection_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __("Code d'ouverture") }}</label>
                <input type="text" wire:model="opening_code" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('opening_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Code DSPS') }}</label>
                <input type="text" wire:model="dsps_code" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('dsps_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Latitude') }}</label>
                <input type="text" wire:model="latitude" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('latitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Longitude') }}</label>
                <input type="text" wire:model="longitude" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('longitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Logo') }}</label>
                @if ($existingLogoPath)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingLogoPath) }}" alt="{{ __('Logo actuel') }}" class="mt-1 mb-2 h-12 w-12 rounded-lg object-cover">
                @endif
                <input type="file" wire:model="logo" class="mt-1 block w-full text-sm">
                @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" wire:model="is_active" class="rounded border-stone-300">
                {{ __('Actif') }}
            </label>

            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" wire:model="is_arabe" class="rounded border-stone-300">
                {{ __('École arabe') }}
            </label>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Fondation') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Type') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($establishments as $establishment)
                    <tr wire:key="establishment-{{ $establishment->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $establishment->name }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $establishment->foundation?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $establishment->type?->label() ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($establishment->is_active)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Actif') }}</span>
                            @else
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ __('Inactif') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-end">
                            @can('update', $establishment)
                                <button wire:click="edit({{ $establishment->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $establishment)
                                <button
                                    wire:click="delete({{ $establishment->id }})"
                                    wire:confirm="{{ __('Supprimer cet établissement ?') }}"
                                    class="ms-3 text-red-500 hover:text-red-700"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun établissement.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
