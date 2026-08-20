<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Directions</h1>

        @can('create', \App\Domain\Establishments\Models\Direction::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                Nouvelle direction
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-stone-700">Code</label>
                <input type="text" wire:model="code" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-stone-700">Nom de la direction</label>
                <input type="text" wire:model="direction_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('direction_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">Adresse</label>
                <input type="text" wire:model="address" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Téléphone</label>
                <input type="text" wire:model="phone_number" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('phone_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Email</label>
                <input type="email" wire:model="email" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">Localisation</label>
                <input type="text" wire:model="location" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('location') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">Logo</label>
                @if ($existingLogoPath)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingLogoPath) }}" alt="Logo actuel" class="mt-1 mb-2 h-12 w-12 rounded-lg object-cover">
                @endif
                <input type="file" wire:model="logo" class="mt-1 block w-full text-sm">
                @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Code</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Nom</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Adresse</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Téléphone</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Email</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Localisation</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($directions as $direction)
                    <tr wire:key="direction-{{ $direction->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $direction->code }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $direction->direction_name }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $direction->address }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $direction->phone_number }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $direction->email }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $direction->location }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            @can('update', $direction)
                                <button wire:click="edit({{ $direction->id }})" class="text-stone-500 hover:text-stone-900">Modifier</button>
                            @endcan
                            @can('delete', $direction)
                                <button
                                    wire:click="delete({{ $direction->id }})"
                                    wire:confirm="Supprimer cette direction ?"
                                    class="ml-3 text-red-500 hover:text-red-700"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-stone-500">Aucune direction.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
