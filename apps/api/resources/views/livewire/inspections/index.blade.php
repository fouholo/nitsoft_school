<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Inspections') }}</h1>

        @can('create', \App\Domain\Establishments\Models\Inspection::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                {{ __('Nouvelle inspection') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Code IEP') }}</label>
                <input type="text" wire:model="codeiep" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('codeiep') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-stone-700">{{ __("Nom de l'inspection") }}</label>
                <input type="text" wire:model="inspection_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('inspection_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Adresse') }}</label>
                <input type="text" wire:model="address" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Téléphone') }}</label>
                <input type="text" wire:model="phone_number" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('phone_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Email') }}</label>
                <input type="email" wire:model="email" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Localisation') }}</label>
                <input type="text" wire:model="location" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('location') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Direction de rattachement') }}</label>
                <select wire:model="uid_direction" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($directions as $direction)
                        <option value="{{ $direction->uid_serveur }}">{{ $direction->code }} — {{ $direction->direction_name }}</option>
                    @endforeach
                </select>
                @error('uid_direction') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

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

    <div class="mt-6 overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Code IEP') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Adresse') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Téléphone') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Email') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Localisation') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Direction') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($inspections as $inspection)
                    <tr wire:key="inspection-{{ $inspection->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $inspection->codeiep }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $inspection->inspection_name }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $inspection->address }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $inspection->phone_number }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $inspection->email }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $inspection->location }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $inspection->direction?->direction_name }}</td>
                        <td class="px-4 py-2 text-end whitespace-nowrap">
                            @can('update', $inspection)
                                <button wire:click="edit({{ $inspection->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $inspection)
                                <button
                                    wire:click="delete({{ $inspection->id }})"
                                    wire:confirm="{{ __('Supprimer cette inspection ?') }}"
                                    class="ms-3 text-red-500 hover:text-red-700"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune inspection.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
