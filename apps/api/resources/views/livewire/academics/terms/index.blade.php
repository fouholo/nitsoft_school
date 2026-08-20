<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Périodes</h1>

        @can('create', \App\Domain\Academics\Models\Term::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                Nouvelle période
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-5">
            <div>
                <label class="block text-sm font-medium text-stone-700">Libellé</label>
                <input type="text" wire:model="label" placeholder="Trimestre 1" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Ordre</label>
                <input type="number" min="1" wire:model="sequence" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('sequence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Début</label>
                <input type="date" wire:model="starts_on" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('starts_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Fin</label>
                <input type="date" wire:model="ends_on" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('ends_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Année scolaire</label>
                <select wire:model="school_year_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-5">
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
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Libellé</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Période</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Année scolaire</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($terms as $term)
                    <tr wire:key="term-{{ $term->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $term->label }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $term->starts_on->format('d/m/Y') }} — {{ $term->ends_on->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $term->schoolYear?->label }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $term)
                                <button wire:click="edit({{ $term->id }})" class="text-stone-500 hover:text-stone-900">Modifier</button>
                            @endcan
                            @can('delete', $term)
                                <button
                                    wire:click="delete({{ $term->id }})"
                                    wire:confirm="Supprimer cette période ?"
                                    class="ml-3 text-red-500 hover:text-red-700"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">Aucune période.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
