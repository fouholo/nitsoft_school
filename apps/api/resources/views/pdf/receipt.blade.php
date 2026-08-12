<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
        .receipt-number { text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 20px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.meta td { padding: 4px 0; }
        table.meta td.label { color: #64748b; width: 150px; }
        table.amount { width: 100%; border-collapse: collapse; }
        table.amount td { padding: 8px; border: 1px solid #cbd5e1; }
        table.amount td.label { background-color: #f1f5f9; font-weight: bold; width: 150px; }
        table.amount td.value { font-size: 16px; font-weight: bold; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.reports-header', ['establishment' => $payment->establishment, 'generalInformation' => $generalInformation])

    <p style="text-align:center; font-weight:bold; margin:0 0 20px;">Reçu de paiement</p>

    <p class="receipt-number">N&deg; {{ $payment->receipt?->receipt_number }}</p>

    <table class="meta">
        <tr>
            <td class="label">Élève</td>
            <td>{{ $payment->student->last_name }} {{ $payment->student->first_name }}</td>
            <td class="label">Date de paiement</td>
            <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Facture</td>
            <td>{{ $payment->invoice->label }}</td>
            <td class="label">Moyen de paiement</td>
            <td>{{ $payment->method }}</td>
        </tr>
        <tr>
            <td class="label">Référence</td>
            <td>{{ $payment->reference ?? '—' }}</td>
            <td class="label">Encaissé par</td>
            <td>{{ $payment->receivedBy?->name }}</td>
        </tr>
    </table>

    <table class="amount">
        <tr>
            <td class="label">Montant reçu</td>
            <td class="value">{{ number_format((float) $payment->amount, 2) }}</td>
        </tr>
    </table>

    <p class="footer">Document généré automatiquement — {{ config('app.name') }}</p>
</body>
</html>
