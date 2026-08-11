<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Inspections</h1>

        @can('create', \App\Domain\Establishments\Models\Inspection::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            >
                Nouvelle inspection
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Code</label>
                <input type="text" wire:model="code" @disabled($editingCode !== null) class="mt-1 block w-full rounded-md border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-500">
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Libellé</label>
                <input type="text" wire:model="libelle" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('libelle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Code</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Libellé</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($inspections as $inspection)
                    <tr wire:key="inspection-{{ $inspection->code }}">
                        <td class="px-4 py-2 text-slate-900">{{ $inspection->code }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $inspection->libelle }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $inspection)
                                <button wire:click="edit('{{ $inspection->code }}')" class="text-slate-500 hover:text-slate-900">Modifier</button>
                            @endcan
                            @can('delete', $inspection)
                                <button
                                    wire:click="delete('{{ $inspection->code }}')"
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
                        <td colspan="3" class="px-4 py-6 text-center text-slate-500">Aucune inspection.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
