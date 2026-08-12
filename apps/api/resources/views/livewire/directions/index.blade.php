<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Directions</h1>

        @can('create', \App\Domain\Establishments\Models\Direction::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            >
                Nouvelle direction
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Code</label>
                <input type="text" wire:model="code" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-slate-700">Nom de la direction</label>
                <input type="text" wire:model="direction_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('direction_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Adresse</label>
                <input type="text" wire:model="address" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Téléphone</label>
                <input type="text" wire:model="phone_number" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('phone_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Email</label>
                <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Localisation</label>
                <input type="text" wire:model="location" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('location') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

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

    <div class="mt-6 overflow-x-auto rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Code</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Nom</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Adresse</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Téléphone</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Email</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Localisation</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($directions as $direction)
                    <tr wire:key="direction-{{ $direction->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $direction->code }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $direction->direction_name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $direction->address }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $direction->phone_number }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $direction->email }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $direction->location }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            @can('update', $direction)
                                <button wire:click="edit({{ $direction->id }})" class="text-slate-500 hover:text-slate-900">Modifier</button>
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
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">Aucune direction.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
