<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Inspections</h1>

        @can('create', \App\Domain\Establishments\Models\Inspection::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                Nouvelle inspection
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-stone-700">Code IEP</label>
                <input type="text" wire:model="codeiep" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('codeiep') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-stone-700">Nom de l'inspection</label>
                <input type="text" wire:model="inspection_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('inspection_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                <label class="block text-sm font-medium text-stone-700">Direction de rattachement</label>
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
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Code IEP</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Nom</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Adresse</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Téléphone</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Email</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Localisation</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Direction</th>
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
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            @can('update', $inspection)
                                <button wire:click="edit({{ $inspection->id }})" class="text-stone-500 hover:text-stone-900">Modifier</button>
                            @endcan
                            @can('delete', $inspection)
                                <button
                                    wire:click="delete({{ $inspection->id }})"
                                    wire:confirm="Supprimer cette inspection ?"
                                    class="ml-3 text-red-500 hover:text-red-700"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-stone-500">Aucune inspection.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
