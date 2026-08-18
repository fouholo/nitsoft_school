<div>
    <a href="{{ route('students.show', $enrollment->student) }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Retour à la fiche élève</a>

    <div class="mt-2 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $enrollment->student?->last_name }} {{ $enrollment->student?->first_name }}</h1>
            <p class="text-sm text-slate-500">
                {{ $enrollment->classroom?->name }} — {{ $enrollment->schoolYear?->label }} —
                Dû {{ money($totalDue) }} —
                Payé {{ money((float) $enrollment->total_paid) }}
            </p>
        </div>

        <div class="flex gap-2">
            @can('create', \App\Domain\Billing\Models\Payment::class)
                <button type="button" wire:click="editAmounts" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Modifier les montants
                </button>
                <button type="button" wire:click="addPayment" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer un paiement
                </button>
            @endcan
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total dû</p>
            <p class="text-sm text-slate-900">{{ money($totalDue) }}</p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total versé</p>
            <p class="text-sm text-slate-900">{{ money((float) $enrollment->total_paid) }}</p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Solde</p>
            @if ($balance > 0)
                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Reste {{ money($balance) }}</span>
            @else
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Soldé</span>
            @endif
        </div>
    </div>

    @if ($showAmountsForm)
        <form wire:submit="saveAmounts" class="mt-4 grid grid-cols-2 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Frais d'inscription</label>
                <input type="number" step="0.01" wire:model="registration_amount" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('registration_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @foreach (range(1, 7) as $position)
                <div>
                    <label class="block text-sm font-medium text-slate-700">Tranche {{ $position }}</label>
                    <input type="number" step="0.01" wire:model="installment_amounts.{{ $position }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    @error('installment_amounts.' . $position) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="col-span-2 flex items-end gap-2 sm:col-span-4">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancelAmounts" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

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

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Détail des montants dus</h2>

    <div class="mt-2 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Poste</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Échéance</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Montant</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr>
                    <td class="px-4 py-2 text-slate-900">Frais d'inscription</td>
                    <td class="px-4 py-2 text-slate-600">—</td>
                    <td class="px-4 py-2 text-slate-600">{{ money($registrationAmount) }}</td>
                    <td class="px-4 py-2"></td>
                </tr>
                @foreach ($tuitionInstallments as $installment)
                    @php
                        [$statusLabel, $statusClasses] = match ($installment['status']) {
                            'paid' => ['Payé', 'bg-emerald-100 text-emerald-700'],
                            'partial_late' => ['En cours', 'bg-orange-100 text-orange-700'],
                            'partial_upcoming' => ['En cours', 'bg-blue-100 text-blue-700'],
                            'late' => ['En retard', 'bg-red-100 text-red-700'],
                            default => ['Dû', 'bg-slate-100 text-slate-700'],
                        };
                    @endphp
                    <tr>
                        <td class="px-4 py-2 text-slate-900">Tranche {{ $installment['position'] }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $installment['due_date']->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ money($installment['amount']) }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClasses }}">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

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
                        <td class="px-4 py-2 text-slate-600">{{ money((float) $payment->amount) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $payment->method }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $payment->receiptNumber() }}</td>
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
