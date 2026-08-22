<div>
    <a href="{{ route('students.show', $enrollment->student) }}" class="text-sm text-stone-500 hover:text-stone-900">&larr; {{ __('Retour à la fiche élève') }}</a>

    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-stone-900">{{ $enrollment->student?->last_name }} {{ $enrollment->student?->first_name }}</h1>
            <p class="text-sm text-stone-500">
                {{ $enrollment->classroom?->name }} — {{ $enrollment->schoolYear?->label }}
            </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            @can('create', \App\Domain\Billing\Models\Payment::class)
                <button type="button" wire:click="editAmounts" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm font-medium text-stone-700 hover:bg-stone-50">
                    {{ __('Modifier les montants') }}
                </button>
                <button type="button" wire:click="addPayment" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer un paiement') }}
                </button>
            @endcan
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Total dû') }}</p>
            <p class="text-sm text-stone-900">{{ money($totalDue) }}</p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Total versé') }}</p>
            <p class="text-sm text-stone-900">{{ money((float) $enrollment->total_paid) }}</p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Solde') }}</p>
            @if ($balance > 0)
                <p class="text-lg font-semibold text-red-700">{{ __('Reste :amount', ['amount' => money($balance)]) }}</p>
            @else
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Soldé') }}</span>
            @endif
        </div>
    </div>

    @if ($showAmountsForm)
        <form wire:submit="saveAmounts" class="mt-4 grid grid-cols-2 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            @if ((float) $enrollment->total_paid > 0)
                <div class="col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 sm:col-span-4">
                    {{ __('Des paiements ont déjà été enregistrés sur cette inscription (:amount versés). Modifier ces montants peut affecter le calcul du solde déjà couvert par ces paiements.', ['amount' => money((float) $enrollment->total_paid)]) }}
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __("Frais d'inscription") }}</label>
                <input type="number" step="0.01" wire:model="registration_amount" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('registration_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @foreach (range(1, 7) as $position)
                <div>
                    <label class="block text-sm font-medium text-stone-700">{{ __('Tranche :number', ['number' => $position]) }}</label>
                    <input type="number" step="0.01" wire:model="installment_amounts.{{ $position }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    @error('installment_amounts.' . $position) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="col-span-2 flex items-end gap-2 sm:col-span-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancelAmounts" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    @if ($showPaymentForm)
        <form wire:submit="savePayment" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Montant') }}</label>
                <input type="number" step="0.01" wire:model="amount" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Moyen') }}</label>
                <select wire:model="method" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="cash">{{ __('Espèces') }}</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="bank_transfer">{{ __('Virement') }}</option>
                    <option value="card">{{ __('Carte') }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Date') }}</label>
                <input type="date" wire:model="paid_at" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('paid_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Référence') }}</label>
                <input type="text" wire:model="reference" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            </div>

            <div class="rounded-lg bg-stone-50 px-3 py-2 text-sm text-stone-600 sm:col-span-4" x-data>
                {{ __('Solde actuel :') }} <span class="font-medium text-stone-900">{{ money($balance) }}</span>
                — {{ __('Nouveau solde après ce paiement :') }} <span class="font-medium text-stone-900" x-text="new Intl.NumberFormat('fr-FR').format(Math.max({{ $balance }} - (parseFloat($wire.amount) || 0), 0)) + ' F CFA'"></span>
            </div>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" wire:confirm="{{ __('Confirmer l\'enregistrement de ce paiement ?') }}" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancelPayment" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Détail des montants dus') }}</h2>

    <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Poste') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Échéance') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Montant') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Versé') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr>
                        <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ __("Frais d'inscription") }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-stone-600">—</td>
                        <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ money($registrationAmount) }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ money($registrationAmountPaid) }}</td>
                        <td class="whitespace-nowrap px-4 py-2"></td>
                    </tr>
                    @foreach ($tuitionInstallments as $installment)
                        @php
                            [$statusLabel, $statusClasses] = match ($installment['status']) {
                                'paid' => [__('Soldé'), 'bg-emerald-100 text-emerald-700'],
                                'partial_late' => [__('En cours'), 'bg-orange-100 text-orange-700'],
                                'partial_upcoming' => [__('En cours'), 'bg-blue-100 text-blue-700'],
                                'late' => [__('En retard'), 'bg-red-100 text-red-700'],
                                default => [__('Dû'), 'bg-stone-100 text-stone-700'],
                            };
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ __('Tranche :number', ['number' => $installment['position']]) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $installment['due_date']->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ money($installment['amount']) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ money($installment['paid']) }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClasses }}">{{ $statusLabel }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Paiements') }}</h2>

    <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Montant') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Moyen') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Reçu') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Encaissé par') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($payments as $payment)
                        <tr wire:key="payment-{{ $payment->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $payment->paid_at->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ money((float) $payment->amount) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $payment->methodLabel() }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $payment->receiptNumber() }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $payment->receivedBy?->name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">
                                @can('view', $payment)
                                    <a href="{{ route('billing.payments.receipt', $payment) }}" target="_blank" class="inline-flex min-h-11 items-center text-stone-700 hover:text-stone-900">{{ __('Voir le reçu') }}</a>
                                    &middot;
                                    <a href="{{ route('billing.payments.receipt', ['payment' => $payment, 'download' => 1]) }}" class="inline-flex min-h-11 items-center text-stone-700 hover:text-stone-900">{{ __('Télécharger') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun paiement enregistré.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
