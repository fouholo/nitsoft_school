<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Dépenses</h1>

        @can('create', \App\Domain\Billing\Models\Expense::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                Nouvelle dépense
            </button>
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <label class="block text-sm font-medium text-stone-700">Mois</label>
            <input type="month" wire:model.live="month" class="mt-1 rounded-lg border-stone-300 text-sm">
        </div>

        <div class="text-right">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">Total {{ $month !== '' ? 'du mois' : 'affiché' }}</p>
            <p class="text-lg font-semibold text-stone-900">{{ money((float) $total) }}</p>
        </div>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">Libellé</label>
                <input type="text" wire:model="label" placeholder="Fournitures de bureau" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Montant</label>
                <input type="number" step="0.01" wire:model="amount" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">Date</label>
                <input type="date" wire:model="spent_at" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('spent_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-3">
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
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Libellé</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Montant</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Date</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Saisie par</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($expenses as $expense)
                        <tr wire:key="expense-{{ $expense->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $expense->label }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ money((float) $expense->amount) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $expense->spent_at->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $expense->recordedBy->name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                @can('delete', $expense)
                                    <button
                                        wire:click="delete({{ $expense->id }})"
                                        wire:confirm="Supprimer la dépense « {{ $expense->label }} » ?"
                                        class="inline-flex min-h-11 items-center text-red-600 hover:text-red-700"
                                    >
                                        Supprimer
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-stone-500">Aucune dépense.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-stone-200 px-4 py-3">
            {{ $expenses->links() }}
        </div>
    </div>
</div>
