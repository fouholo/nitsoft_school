<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Dépenses') }}</h1>

        @can('create', \App\Domain\Billing\Models\Expense::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvelle dépense') }}
            </button>
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <label class="block text-sm font-medium text-stone-700">{{ __('Mois') }}</label>
            <input type="month" wire:model.live="month" class="mt-1 rounded-lg border-stone-300 text-sm">
        </div>

        <div class="text-end">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $month !== '' ? __('Total du mois') : __('Total affiché') }}</p>
            <p class="text-lg font-semibold text-stone-900">{{ money((float) $total) }}</p>
        </div>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Libellé') }}</label>
                <input type="text" wire:model="label" placeholder="{{ __('Fournitures de bureau') }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Montant') }}</label>
                <input type="number" step="0.01" wire:model="amount" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Date') }}</label>
                <input type="date" wire:model="spent_at" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('spent_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-3">
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
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Libellé') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Montant') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Saisie par') }}</th>
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
                            <td class="whitespace-nowrap px-4 py-2 text-end">
                                @can('delete', $expense)
                                    <button
                                        wire:click="delete({{ $expense->id }})"
                                        wire:confirm="{{ __('Supprimer la dépense « :label » ?', ['label' => $expense->label]) }}"
                                        class="inline-flex min-h-11 items-center text-red-600 hover:text-red-700"
                                    >
                                        {{ __('Supprimer') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune dépense.') }}</td>
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
