<div>
    <a href="{{ route('guardian-portal.dashboard') }}" class="text-sm text-stone-500 hover:text-stone-900">&larr; {{ __('Mes enfants') }}</a>

    <h1 class="mt-2 text-2xl font-semibold text-stone-900">{{ __('Facturation — :name', ['name' => $student->last_name.' '.$student->first_name]) }}</h1>

    @if (! $enrollment)
        <p class="mt-6 text-sm text-stone-500">{{ __('Aucune inscription active pour cette année scolaire.') }}</p>
    @else
        <div class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-stone-400">{{ __('Total dû') }}</p>
                <p class="text-sm text-stone-900">{{ money($totalDue) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-stone-400">{{ __('Total versé') }}</p>
                <p class="text-sm text-stone-900">{{ money($totalPaid) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-stone-400">{{ __('Solde') }}</p>
                @if ($balance > 0)
                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('Reste :amount', ['amount' => money($balance)]) }}</span>
                @else
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Soldé') }}</span>
                @endif
            </div>
        </div>

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Détail des montants dus') }}</h2>

        <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Poste') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Échéance') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Montant') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr>
                        <td class="px-4 py-2 text-stone-900">{{ __("Frais d'inscription") }}</td>
                        <td class="px-4 py-2 text-stone-600">—</td>
                        <td class="px-4 py-2 text-stone-600">{{ money($registrationAmount) }}</td>
                    </tr>
                    @foreach ($tuitionInstallments as $installment)
                        <tr>
                            <td class="px-4 py-2 text-stone-900">{{ __('Tranche :number', ['number' => $installment['position']]) }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $installment['due_date']->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ money($installment['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Paiements') }}</h2>

        <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Montant') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Moyen') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Reçu') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($payments as $payment)
                        <tr wire:key="guardian-payment-{{ $payment->id }}">
                            <td class="px-4 py-2 text-stone-900">{{ $payment->paid_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ money((float) $payment->amount) }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $payment->methodLabel() }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $payment->receiptNumber() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun paiement enregistré.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
