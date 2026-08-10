<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Dépenses</h1>

        @can('create', \App\Domain\Billing\Models\Expense::class)
            <button type="button" wire:click="create" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Nouvelle dépense
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Libellé</label>
                <input type="text" wire:model="label" placeholder="Fournitures de bureau" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Montant</label>
                <input type="number" step="0.01" wire:model="amount" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Date</label>
                <input type="date" wire:model="spent_at" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('spent_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-3">
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
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Libellé</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Montant</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Date</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Saisie par</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($expenses as $expense)
                    <tr wire:key="expense-{{ $expense->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $expense->label }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ number_format((float) $expense->amount, 2) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $expense->spent_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $expense->recordedBy->name }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('delete', $expense)
                                <button
                                    wire:click="delete({{ $expense->id }})"
                                    wire:confirm="Supprimer cette dépense ?"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucune dépense.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
