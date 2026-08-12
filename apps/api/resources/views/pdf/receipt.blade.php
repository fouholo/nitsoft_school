<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 16px; margin: 0 0 4px; }
        .header p { margin: 0; color: #64748b; }
        .divider { border: none; border-top: 3px solid #94a3b8; margin: 0 0 20px; }
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
    <div class="header">
        @if ($payment->establishment->logo_path)
            <img src="{{ public_path('storage/'.$payment->establishment->logo_path) }}" style="height: 60px; margin-bottom: 8px;">
        @endif
        <h1>{{ $payment->establishment->name }}</h1>
        <p>Reçu de paiement</p>
    </div>

    <hr class="divider">

    <p class="receipt-number">N&deg; {{ $payment->receipt?->receipt_number }}</p>

    <table class="meta">
        <tr>
            <td class="label">Élève</td>
            <td>{{ $payment->student->last_name }} {{ $payment->student->first_name }}</td>
        </tr>
        <tr>
            <td class="label">Date de paiement</td>
            <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Facture</td>
            <td>{{ $payment->invoice->label }}</td>
        </tr>
        <tr>
            <td class="label">Moyen de paiement</td>
            <td>{{ $payment->method }}</td>
        </tr>
        <tr>
            <td class="label">Référence</td>
            <td>{{ $payment->reference ?? '—' }}</td>
        </tr>
        <tr>
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
