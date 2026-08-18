<div>
    <a href="{{ route('guardian-portal.dashboard') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Mes enfants</a>

    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Facturation — {{ $student->last_name }} {{ $student->first_name }}</h1>

    @if (! $enrollment)
        <p class="mt-6 text-sm text-slate-500">Aucune inscription active pour cette année scolaire.</p>
    @else
        <div class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total dû</p>
                <p class="text-sm text-slate-900">{{ money($totalDue) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total versé</p>
                <p class="text-sm text-slate-900">{{ money($totalPaid) }}</p>
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

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Détail des montants dus</h2>

        <div class="mt-2 overflow-hidden rounded-md border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Poste</th>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Échéance</th>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Montant</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="px-4 py-2 text-slate-900">Frais d'inscription</td>
                        <td class="px-4 py-2 text-slate-600">—</td>
                        <td class="px-4 py-2 text-slate-600">{{ money($registrationAmount) }}</td>
                    </tr>
                    @foreach ($tuitionInstallments as $installment)
                        <tr>
                            <td class="px-4 py-2 text-slate-900">Tranche {{ $installment['position'] }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $installment['due_date']->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ money($installment['amount']) }}</td>
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr wire:key="guardian-payment-{{ $payment->id }}">
                            <td class="px-4 py-2 text-slate-900">{{ $payment->paid_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ money((float) $payment->amount) }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $payment->method }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $payment->receiptNumber() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">Aucun paiement enregistré.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
