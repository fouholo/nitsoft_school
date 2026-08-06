<div>
    <a href="{{ route('billing.invoices.index') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Retour aux factures</a>

    <div class="mt-2 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $invoice->label }}</h1>
            <p class="text-sm text-slate-500">
                {{ $invoice->student?->last_name }} {{ $invoice->student?->first_name }} —
                Dû {{ number_format((float) $invoice->amount_due, 2) }} —
                Payé {{ number_format((float) $invoice->amount_paid, 2) }} —
                Statut {{ $invoice->status }}
            </p>
        </div>

        @can('create', \App\Domain\Billing\Models\Payment::class)
            @if ($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                <button type="button" wire:click="addPayment" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer un paiement
                </button>
            @endif
        @endcan
    </div>

    @if ($showPaymentForm)
        <form wire:submit="savePayment" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Montant</label>
                <input type="number" step="0.01" wire:model="amount" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Moyen</label>
                <select wire:model="method" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="cash">Espèces</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="bank_transfer">Virement</option>
                    <option value="card">Carte</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Date</label>
                <input type="date" wire:model="paid_at" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('paid_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Référence</label>
                <input type="text" wire:model="reference" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancelPayment" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Paiements</h2>

    <div class="mt-2 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Date</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Montant</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Moyen</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Reçu</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Encaissé par</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payments as $payment)
                    <tr wire:key="payment-{{ $payment->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $payment->paid_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $payment->method }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $payment->receipt?->receipt_number }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $payment->receivedBy?->name }}</td>
                        <td class="px-4 py-2 text-slate-600">
                            @can('view', $payment)
                                <a href="{{ route('billing.payments.receipt', $payment) }}" target="_blank" class="text-slate-700 hover:text-slate-900">Voir le reçu</a>
                                &middot;
                                <a href="{{ route('billing.payments.receipt', ['payment' => $payment, 'download' => 1]) }}" class="text-slate-700 hover:text-slate-900">Télécharger</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">Aucun paiement enregistré.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
