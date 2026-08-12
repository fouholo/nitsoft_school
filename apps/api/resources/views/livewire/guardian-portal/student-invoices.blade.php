<div>
    <a href="{{ route('guardian-portal.dashboard') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Mes enfants</a>

    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Factures — {{ $student->last_name }} {{ $student->first_name }}</h1>

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Libellé</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Échéance</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Dû</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Payé</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $invoice)
                    <tr wire:key="guardian-invoice-{{ $invoice->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $invoice->label }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $invoice->due_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ money((float) $invoice->amount_due) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ money((float) $invoice->amount_paid) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $invoice->status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucune facture.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
