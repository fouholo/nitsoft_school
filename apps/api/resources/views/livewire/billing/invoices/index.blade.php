<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Factures</h1>

        @can('create', \App\Domain\Billing\Models\Invoice::class)
            <button type="button" wire:click="create" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                Nouvelle facture
            </button>
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap gap-4">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Rechercher un élève..."
            class="block w-full max-w-sm rounded-md border-slate-300 text-sm"
        >
        <select wire:model.live="statusFilter" class="rounded-md border-slate-300 text-sm">
            <option value="">Tous les statuts</option>
            <option value="pending">En attente</option>
            <option value="partially_paid">Partiellement payée</option>
            <option value="paid">Payée</option>
            <option value="overdue">En retard</option>
            <option value="cancelled">Annulée</option>
        </select>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">Élève</label>
                <select wire:model="student_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}">{{ $student->last_name }} {{ $student->first_name }}</option>
                    @endforeach
                </select>
                @error('student_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Libellé</label>
                <input type="text" wire:model="label" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Montant dû</label>
                <input type="number" step="0.01" wire:model="amount_due" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('amount_due') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Échéance</label>
                <input type="date" wire:model="due_date" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Année scolaire</label>
                <select wire:model="school_year_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
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
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Élève</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Libellé</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Dû</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Payé</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Statut</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $invoice)
                    <tr wire:key="invoice-{{ $invoice->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $invoice->student?->last_name }} {{ $invoice->student?->first_name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $invoice->label }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ number_format((float) $invoice->amount_due, 2) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                        <td class="px-4 py-2">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700' => $invoice->status === 'paid',
                                'bg-amber-100 text-amber-700' => $invoice->status === 'partially_paid',
                                'bg-slate-100 text-slate-700' => $invoice->status === 'pending',
                                'bg-red-100 text-red-700' => in_array($invoice->status, ['overdue', 'cancelled']),
                            ])>
                                {{ $invoice->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('billing.invoices.show', $invoice) }}" class="text-slate-500 hover:text-slate-900">
                                Détails
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">Aucune facture.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $invoices->links() }}
        </div>
    </div>
</div>
